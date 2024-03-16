<?php

namespace avadim\FastExcelWriter\Writer;

use avadim\FastExcelWriter\Charts\Axis;
use avadim\FastExcelWriter\Charts\Chart;
use avadim\FastExcelWriter\Charts\DataSeries;
use avadim\FastExcelWriter\Charts\DataSeriesLabels;
use avadim\FastExcelWriter\Charts\DataSeriesValues;
use avadim\FastExcelWriter\Charts\GridLines;
use avadim\FastExcelWriter\Charts\Layout;
use avadim\FastExcelWriter\Charts\Legend;
use avadim\FastExcelWriter\Charts\PlotArea;
use avadim\FastExcelWriter\Charts\Title;
use avadim\FastExcelWriter\Exceptions\Exception;

class ChartWriter extends FileWriter
{
    private int $_seriesIndex;

    /**
     * Write chart{n}.xml
     *
     * @param Chart $chart
     *
     * @return void
     */
    public function writeChartXml(Chart $chart)
    {
        $this->write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        $this->startElement('c:chartSpace');
        $this->writeAttribute('xmlns:c', 'http://schemas.openxmlformats.org/drawingml/2006/chart');
        $this->writeAttribute('xmlns:a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $this->writeAttribute('xmlns:r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $this->startElement('c:date1904');
        $this->writeAttribute('val', 0);
        $this->endElement();
        $this->startElement('c:lang');
        $this->writeAttribute('val', "en-GB");
        $this->endElement();
        $this->startElement('c:roundedCorners');
        $this->writeAttribute('val', 0);
        $this->endElement();

        $this->startElement('mc:AlternateContent');
        $this->writeAttribute('xmlns:mc', 'http://schemas.openxmlformats.org/markup-compatibility/2006');

        $this->startElement('mc:Choice');
        $this->writeAttribute('xmlns:c14', 'http://schemas.microsoft.com/office/drawing/2007/8/2/chart');
        $this->writeAttribute('Requires', 'c14');

        $this->startElement('c14:style');
        $this->writeAttribute('val', '102');
        $this->endElement();
        $this->endElement();

        $this->startElement('mc:Fallback');
        $this->startElement('c:style');
        $this->writeAttribute('val', '2');
        $this->endElement();
        $this->endElement();

        $this->endElement();

        $this->startElement('c:chart');

        $this->writeTitle($chart->getTitle(), false);

        $this->writeElementAttr('<c:autoTitleDeleted val="0"/>');

        $this->writePlotArea($chart);

        if ($chart->getLegend()) {
            $this->writeLegend($chart->getLegend());
        }

        $this->startElement('c:plotVisOnly');
        $this->writeAttribute('val', 1);
        $this->endElement();

        $this->startElement('c:dispBlanksAs');
        $this->writeAttribute('val', "gap");
        $this->endElement();

        $this->startElement('c:showDLblsOverMax');
        $this->writeAttribute('val', 0);
        $this->endElement();

        $this->endElement();

        //$this->writePrintSettings($fileWriter);

        $this->endElement();

        $this->flush(true);
    }

    /**
     * @param Title|null $title
     * @param bool $withLayout
     *
     * @return void
     */
    private function writeTitle(?title $title, bool $withLayout)
    {
        if ($title) {
            $this->startElement('c:title');
            $this->startElement('c:tx');
            $this->startElement('c:rich');

            $this->writeElement('a:bodyPr');
            $this->writeElement('a:lstStyle');

            $this->startElement('a:p');
            $this->startElement('a:r');

            $caption = $title->getCaption();
            if ($caption) {
                $this->startElement('a:t');
                // $this->writeAttribute('xml:space', 'preserve');
                $this->writeRawData(Writer::xmlSpecialChars($caption));
                $this->endElement(); // a:t
            }
            else {
                $this->writeElementAttr('<a:t/>');
            }
            $this->endElement(); // a:r
            $this->endElement(); // a:p

            $this->endElement(); // c:rich
            $this->endElement(); // c:tx

            if ($withLayout) {
                $this->writeLayout($title->getLayout());
            }
            else {
                $this->writeElement('<c:layout/>');
            }

            $this->startElement('c:overlay');
            $this->writeAttribute('val', 0);
            $this->endElement();

            $this->endElement(); // c:title
        }
    }

    /**
     * Write Chart Legend
     *
     * @param Legend $legend
     *
     * @throws Exception
     */
    private function writeLegend(Legend $legend)
    {
        $this->startElement('c:legend');

        $this->startElement('c:legendPos');
        $this->writeAttribute('val', $legend->getPosition());
        $this->endElement();

        $this->writeLayout($legend->getLayout());

        $this->startElement('c:overlay');
        $this->writeAttribute('val', ($legend->getOverlay()) ? '1' : '0');
        $this->endElement();

        $this->startElement('c:txPr');
        $this->startElement('a:bodyPr');
        $this->endElement();

        $this->startElement('a:lstStyle');
        $this->endElement();

        $this->startElement('a:p');
        $this->startElement('a:pPr');
        $this->writeAttribute('rtl', 0);

        $this->startElement('a:defRPr');
        $this->endElement();
        $this->endElement();

        $this->startElement('a:endParaRPr');
        $this->writeAttribute('lang', "en-US");
        $this->endElement();

        $this->endElement();
        $this->endElement();

        $this->endElement(); // c:legend
    }

    /**
     * @param Chart $chart
     *
     * @return void
     */
    protected function writePlotArea(Chart $chart)
    {
        $plotArea = $chart->getPlotArea();
        $categoryAxisTitle = $chart->getCategoryAxisTitle();
        $valueAxisTitle = $chart->getValueAxisTitle();
        $xAxis = $chart->getChartAxisX();
        $yAxis = $chart->getChartAxisY();
        $majorGridlines = $chart->getMajorGridlines();
        $minorGridlines = $chart->getMinorGridlines();

        $id1 = $id2 = 0;
        $this->_seriesIndex = 0;
        $this->startElement('c:plotArea');

        $layout = $plotArea->getLayout();

        $this->writeLayout($layout);

        $chartTypes = $chart->getPlotChartTypes();
        $catIsMultiLevelSeries = $valIsMultiLevelSeries = false;
        $plotGroupingType = '';

        $dataSeries = $plotArea->getPlotDataSeriesByIndex(0);
        foreach ($chartTypes as $chartType) {
            $this->startElement('c:' . $chartType);

            $groupCount = $plotArea->getPlotDataSeriesCount();
            for ($i = 0; $i < $groupCount; ++$i) {
                $dataSeries = $plotArea->getPlotDataSeriesByIndex($i);
                $groupType = $dataSeries->getPlotChartType();
                if ($groupType === $chartType) {
                    $plotStyle = $dataSeries->getPlotStyle();
                    if ($groupType === DataSeries::TYPE_RADAR_CHART) {
                        $this->startElement('c:radarStyle');
                        $this->writeAttribute('val', $plotStyle);
                        $this->endElement();
                    }
                    elseif ($groupType === DataSeries::TYPE_SCATTER_CHART) {
                        $this->startElement('c:scatterStyle');
                        $this->writeAttribute('val', $plotStyle);
                        $this->endElement();
                    }

                    $this->writePlotGroup($dataSeries, $chartType, $catIsMultiLevelSeries, $valIsMultiLevelSeries, $plotGroupingType);
                }
            }

            $this->writeDataLabels($layout);

            if ($chartType === DataSeries::TYPE_LINE_CHART) {
                // Line only, Line3D can't be smoothed
                $this->startElement('c:smooth');
                $this->writeAttribute('val', (int)$dataSeries->getSmoothLine());
                $this->endElement();
            }
            elseif ($chartType === DataSeries::TYPE_BAR_CHART || $chartType === DataSeries::TYPE_BAR_3D_CHART) {
                $this->startElement('c:gapWidth');
                $this->writeAttribute('val', 150);
                $this->endElement();

                if ($plotGroupingType === 'percentStacked' || $plotGroupingType === 'stacked') {
                    $this->startElement('c:overlap');
                    $this->writeAttribute('val', 100);
                    $this->endElement();
                }
            }
            elseif ($chartType === DataSeries::TYPE_BUBBLE_CHART) {
                $this->startElement('c:bubbleScale');
                $this->writeAttribute('val', 25);
                $this->endElement();

                $this->startElement('c:showNegBubbles');
                $this->writeAttribute('val', 0);
                $this->endElement();
            }
            elseif ($chartType === DataSeries::TYPE_STOCK_CHART) {
                $this->startElement('c:hiLowLines');
                $this->endElement();

                $this->startElement('c:upDownBars');

                $this->startElement('c:gapWidth');
                $this->writeAttribute('val', 300);
                $this->endElement();

                $this->startElement('c:upBars');
                $this->endElement();

                $this->startElement('c:downBars');
                $this->endElement();

                $this->endElement();
            }

            //    Generate 2 unique numbers to use for axId values
            //                    $id1 = $id2 = rand(10000000,99999999);
            //                    do {
            //                        $id2 = rand(10000000,99999999);
            //                    } while ($id1 == $id2);
            $id1 = '75091328';
            $id2 = '75089408';

            if ($chartType !== DataSeries::TYPE_PIE_CHART && $chartType !== DataSeries::TYPE_PIE_3D_CHART && $chartType !== DataSeries::TYPE_DONUT_CHART) {
                $this->startElement('c:axId');
                $this->writeAttribute('val', $id1);
                $this->endElement();
                $this->startElement('c:axId');
                $this->writeAttribute('val', $id2);
                $this->endElement();
            }
            else {
                $this->startElement('c:firstSliceAng');
                $this->writeAttribute('val', 0);
                $this->endElement();

                if ($chartType === DataSeries::TYPE_DONUT_CHART) {
                    $this->startElement('c:holeSize');
                    $this->writeAttribute('val', 50);
                    $this->endElement(); // c:holeSize
                }
            }

            $this->endElement();
        }
        if (!empty($chartType) && $chartType !== DataSeries::TYPE_PIE_CHART && $chartType !== DataSeries::TYPE_PIE_3D_CHART && $chartType !== DataSeries::TYPE_DONUT_CHART) {
            if ($chartType === DataSeries::TYPE_BUBBLE_CHART) {
                $this->writeValueAxis($categoryAxisTitle, $chartType, $id1, $id2, $catIsMultiLevelSeries, $xAxis, $yAxis, $majorGridlines, $minorGridlines);
            }
            else {
                $this->writeCategoryAxis($categoryAxisTitle, $chartType, $id1, $id2, $catIsMultiLevelSeries, $xAxis, $yAxis);
            }

            $this->writeValueAxis($valueAxisTitle, $chartType, $id1, $id2, $valIsMultiLevelSeries, $xAxis, $yAxis, $majorGridlines, $minorGridlines);
        }

        $this->endElement(); // c:plotArea
    }

    /**
     * @param Layout|null $layout
     *
     * @return void
     */
    private function writeLayout(?Layout $layout)
    {
        $this->startElement('c:layout');

        if ($layout) {
            $this->startElement('c:manualLayout');

            $layoutTarget = $layout->getLayoutTarget();
            if ($layoutTarget) {
                $this->startElement('c:layoutTarget');
                $this->writeAttribute('val', $layoutTarget);
                $this->endElement();
            }

            $xMode = $layout->getXMode();
            if ($xMode) {
                $this->startElement('c:xMode');
                $this->writeAttribute('val', $xMode);
                $this->endElement();
            }

            $yMode = $layout->getYMode();
            if ($yMode) {
                $this->startElement('c:yMode');
                $this->writeAttribute('val', $yMode);
                $this->endElement();
            }

            $x = $layout->getXPosition();
            if ($x) {
                $this->startElement('c:x');
                $this->writeAttribute('val', $x);
                $this->endElement();
            }

            $y = $layout->getYPosition();
            if ($y) {
                $this->startElement('c:y');
                $this->writeAttribute('val', $y);
                $this->endElement();
            }

            $w = $layout->getWidth();
            if ($w) {
                $this->startElement('c:w');
                $this->writeAttribute('val', $w);
                $this->endElement();
            }

            $h = $layout->getHeight();
            if ($h) {
                $this->startElement('c:h');
                $this->writeAttribute('val', $h);
                $this->endElement();
            }

            $this->endElement();
        }

        $this->endElement();
    }

    /**
     * Write Plot Group (series of related plots)
     *
     * @param DataSeries $dataSeries
     * @param string $chartType Type of plot for data series
     * @param boolean &$catIsMultiLevelSeries Is category a multi-series category
     * @param  boolean &$valIsMultiLevelSeries Is value set a multi-series set
     * @param  string &$plotGroupingType Type of grouping for multi-series values
     */
    private function writePlotGroup(DataSeries $dataSeries, string $chartType, bool &$catIsMultiLevelSeries, &$valIsMultiLevelSeries, &$plotGroupingType)
    {
        if ($chartType === DataSeries::TYPE_BAR_CHART || $chartType === DataSeries::TYPE_BAR_3D_CHART) {
            $this->startElement('c:barDir');
            $this->writeAttribute('val', $dataSeries->getPlotChartDirection());
            $this->endElement();
        }

        if (null !== $dataSeries->getPlotGrouping()) {
            $plotGroupingType = $dataSeries->getPlotGrouping();
            $this->startElement('c:grouping');
            $this->writeAttribute('val', $plotGroupingType);
            $this->endElement();
        }

        // Get these details before the loop, because we can use the count to check for varyColors
        $plotSeriesOrder = $dataSeries->getPlotOrder();
        $plotSeriesCount = count($plotSeriesOrder);

        if ($chartType !== DataSeries::TYPE_RADAR_CHART && $chartType !== DataSeries::TYPE_STOCK_CHART && $chartType !== DataSeries::TYPE_LINE_CHART) {
            if ($chartType === DataSeries::TYPE_PIE_CHART || $chartType === DataSeries::TYPE_PIE_3D_CHART || $chartType === DataSeries::TYPE_DONUT_CHART || $plotSeriesCount > 1) {
                $this->startElement('c:varyColors');
                $this->writeAttribute('val', 1);
                $this->endElement();
            }
            else {
                $this->startElement('c:varyColors');
                $this->writeAttribute('val', 0);
                $this->endElement();
            }
        }

        foreach ($dataSeries->getDataSeriesValues() as $dataSeriesIdx => $dataSeriesValues) {
            if ($dataSeriesValues && $dataSeriesValues->getDataSource()) {

                $this->startElement('c:ser');

                $this->startElement('c:idx');
                $this->writeAttribute('val', $this->_seriesIndex);
                $this->endElement();

                $this->startElement('c:order');
                $this->writeAttribute('val', $this->_seriesIndex);
                $this->endElement();

                $dataColor = $dataSeriesValues->getColor();
                if ($chartType === DataSeries::TYPE_PIE_CHART || $chartType === DataSeries::TYPE_PIE_3D_CHART || $chartType === DataSeries::TYPE_DONUT_CHART) {
                    $count = $dataSeriesValues->getPointCount();
                    $segmentColors = $dataSeriesValues->getSegmentColors();
                    for ($idx = 0; $idx < $count; $idx++) {
                        $this->startElement('c:dPt');
                        $this->startElement('c:idx');
                        $this->writeAttribute('val', $idx);
                        $this->endElement();

                        $this->startElement('c:bubble3D');
                        $this->writeAttribute('val', 0);
                        $this->endElement();

                        $this->startElement('c:spPr');
                        $color = $segmentColors[$idx] ?? null;
                        if ($color) {
                            $this->startElement('a:solidFill');
                            $this->startElement('a:srgbClr');
                            $this->writeAttribute('val', $color);
                            $this->endElement(); // a:srgbClr
                            $this->endElement(); // a:solidFill
                        }
                        $this->endElement(); // c:spPr
                        $this->endElement(); // c:dPt
                    }
                }

                // Labels
                $dataSeriesLabels = $dataSeriesValues->getLabels();
                if ($dataSeriesLabels && $dataSeriesLabels->getDataSource()) {
                    $this->startElement('c:tx');
                    $this->writeDataSeriesLabel($dataSeriesLabels);
                    $this->endElement();
                }

                // Formatting for the points
                if ($chartType === DataSeries::TYPE_LINE_CHART || $chartType === DataSeries::TYPE_STOCK_CHART) {
                    $width = $dataSeriesValues->getWidth();
                    $this->startElement('c:spPr');
                    $this->startElement('a:ln');
                    $this->writeAttribute('w', $width);
                    if ($chartType === DataSeries::TYPE_STOCK_CHART) {
                        $this->startElement('a:noFill');
                        $this->endElement();
                    }
                    elseif ($dataColor) {
                        $this->startElement('a:solidFill');
                        $this->startElement('a:srgbClr');
                        $this->writeAttribute('val', $dataColor);
                        $this->endElement();
                        $this->endElement();
                    }
                    $this->endElement(); // a:ln
                    $this->endElement(); // c:spPr
                }
                elseif ($dataColor) {
                    /* custom colors of data series */
                    $this->startElement('c:spPr');
                    $this->startElement('a:solidFill');
                    $this->startElement('a:srgbClr');
                    $this->writeAttribute('val', $dataColor);
                    $this->endElement();
                    $this->endElement();
                    $this->endElement(); // c:spPr
                }

                if ($chartType === DataSeries::TYPE_LINE_CHART) {
                    $plotSeriesMarker = $dataSeriesValues->getPointMarker();
                    if ($plotSeriesMarker !== null || $dataColor) {
                        $this->startElement('c:marker');

                        if ($plotSeriesMarker > '' && $plotSeriesMarker !== 'none') {
                            $this->writeElementAttr('c:symbol', ['val' => $plotSeriesMarker]);
                            $this->startElement('c:size');
                            $this->writeAttribute('val', 3);
                            $this->endElement();
                        }
                        if ($dataColor) {
                            $this->startElement('c:spPr');
                            $this->startElement('a:solidFill');
                            $this->writeElementAttr('a:srgbClr', ['val' => $dataColor]);
                            $this->endElement(); // a:solidFill

                            $this->startElement('a:ln');
                            $this->startElement('a:solidFill');
                            $this->writeElementAttr('a:srgbClr', ['val' => $dataColor]);
                            $this->endElement(); // a:solidFill
                            $this->endElement();

                            $this->endElement(); // c:spPr
                        }

                        $this->endElement(); // c:marker
                    }
                }

                if ($chartType === DataSeries::TYPE_BAR_CHART || $chartType === DataSeries::TYPE_BAR_3D_CHART || $chartType === DataSeries::TYPE_BUBBLE_CHART) {
                    $this->startElement('c:invertIfNegative');
                    $this->writeAttribute('val', 0);
                    $this->endElement();
                }

                // Category Labels
                $plotSeriesCategories = $dataSeries->getCategoryAxisLabelsByIndex($dataSeriesIdx);
                if ($plotSeriesCategories && $plotSeriesCategories->getDataSource()) {
                    //$catIsMultiLevelSeries = $catIsMultiLevelSeries || $plotSeriesCategories->isMultiLevelSeries();

                    if ($chartType === DataSeries::TYPE_PIE_CHART || $chartType === DataSeries::TYPE_PIE_3D_CHART || $chartType === DataSeries::TYPE_DONUT_CHART) {
                        if ($dataSeries->getPlotStyle()) {
                            $this->startElement('c:explosion');
                            $this->writeAttribute('val', 25);
                            $this->endElement();
                        }
                    }

                    if ($chartType === DataSeries::TYPE_BUBBLE_CHART || $chartType === DataSeries::TYPE_SCATTER_CHART) {
                        $this->startElement('c:xVal');
                    }
                    else {
                        $this->startElement('c:cat');
                    }
                    $this->writeDataSeriesValues($plotSeriesCategories, $chartType, 'str');
                    $this->endElement();
                }

                //    Values
                $valIsMultiLevelSeries = $valIsMultiLevelSeries || $dataSeriesValues->isMultiLevelSeries();
                if ($chartType === DataSeries::TYPE_BUBBLE_CHART || $chartType === DataSeries::TYPE_SCATTER_CHART) {
                    $this->startElement('c:yVal');
                }
                else {
                    $this->startElement('c:val');
                }
                $this->writeDataSeriesValues($dataSeriesValues, $chartType, 'num');
                $this->endElement();

                if ($chartType === DataSeries::TYPE_BUBBLE_CHART) {
                    $this->writeBubbles($dataSeriesValues);
                }

                $this->endElement(); // c:ser
            }
            $this->_seriesIndex++;
        }
    }

    /**
     * Write Plot Series Label
     *
     * @param DataSeriesLabels $plotSeriesLabel
     */
    private function writeDataSeriesLabel(DataSeriesLabels $plotSeriesLabel)
    {
        $source = $plotSeriesLabel->getDataSource();
        if ($plotSeriesLabel->isDataSourceFormula()) {
            $this->startElement('c:strRef');

            $this->startElement('c:f');
            $this->writeRawData(substr($source, 1));
            $this->endElement(); // c:f

            $this->endElement(); // c:strRef
        }
        else {
            $this->startElement('c:v');
            $this->writeRawData($source);
            $this->endElement();
        }
    }

    /**
     * Write Plot Series Values
     *
     * @param DataSeriesValues|DataSeriesLabels $plotSeriesValues
     * @param string $groupType Type of plot for DataSeries
     * @param string $dataType Datatype of series values
     */
    private function writeDataSeriesValues($plotSeriesValues, string $groupType, string $dataType)
    {
        if ($plotSeriesValues->isMultiLevelSeries()) {
            $levelCount = $plotSeriesValues->multiLevelCount();

            $this->startElement('c:multiLvlStrRef');

            $source = $plotSeriesValues->getDataSource();
            if ($source && $source[0] === '=') {
                $source = substr($source, 1);
            }
            $this->startElement('c:f');
            $this->writeRawData($source);
            $this->endElement();

            $this->startElement('c:multiLvlStrCache');

            $this->startElement('c:ptCount');
            $this->writeAttribute('val', $plotSeriesValues->getPointCount());
            $this->endElement();

            for ($level = 0; $level < $levelCount; ++$level) {
                $this->startElement('c:lvl');
                foreach ($plotSeriesValues->getDataValues() as $plotSeriesKey => $plotSeriesValue) {
                    if (isset($plotSeriesValue[$level])) {
                        $this->startElement('c:pt');
                        $this->writeAttribute('idx', $plotSeriesKey);

                        $this->startElement('c:v');
                        $this->writeRawData($plotSeriesValue[$level]);
                        $this->endElement();
                        $this->endElement();
                    }
                }
                $this->endElement(); // c:lvl
            }

            $this->endElement();

            $this->endElement();
        }
        else {
            $this->startElement('c:' . $dataType . 'Ref');

            $source = $plotSeriesValues->getDataSource();
            if ($source && $source[0] === '=') {
                $source = substr($source, 1);
            }
            $this->startElement('c:f');
            $this->writeRawData($source);
            $this->endElement();
/*
            $dataValues = $plotSeriesValues->getDataValues();
            if ($dataValues) {
                $this->startElement('c:' . $dataType . 'Cache');

                if ($groupType !== DataSeries::TYPE_PIECHART && $groupType !== DataSeries::TYPE_PIECHART_3D && $groupType !== DataSeries::TYPE_DONUTCHART) {
                    if (($plotSeriesValues->getFormatCode() !== null) && ($plotSeriesValues->getFormatCode() !== '')) {
                        $this->startElement('c:formatCode');
                        $this->writeRawData($plotSeriesValues->getFormatCode());
                        $this->endElement();
                    }
                }

                $this->startElement('c:ptCount');
                $this->writeAttribute('val', $plotSeriesValues->getPointCount());
                $this->endElement();

                $dataValues = $plotSeriesValues->getDataValues();
                if ($dataValues) {
                    foreach ($dataValues as $plotSeriesKey => $plotSeriesValue) {
                        $this->startElement('c:pt');
                        $this->writeAttribute('idx', $plotSeriesKey);

                        $this->startElement('c:v');
                        $this->writeRawData($plotSeriesValue);
                        $this->endElement();
                        $this->endElement();
                    }
                }

                $this->endElement();
            }
*/
            $this->endElement(); // 'c:' . $dataType . 'Ref'
        }
    }

    /**
     * Write Bubble Chart Details
     *
     * @param DataSeriesValues $plotSeriesValues
     */
    private function writeBubbles(DataSeriesValues $plotSeriesValues)
    {
        $this->startElement('c:bubbleSize');
        $this->startElement('c:numLit');

        $this->startElement('c:formatCode');
        $this->writeRawData('General');
        $this->endElement();

        $this->startElement('c:ptCount');
        $this->writeAttribute('val', $plotSeriesValues->getPointCount());
        $this->endElement();

        $dataValues = $plotSeriesValues->getDataValues();
        foreach ($dataValues as $plotSeriesKey => $plotSeriesValue) {
            $this->startElement('c:pt');
            $this->writeAttribute('idx', $plotSeriesKey);
            $this->startElement('c:v');
            $this->writeRawData(1);
            $this->endElement();
            $this->endElement();
        }

        $this->endElement();
        $this->endElement();

        $this->startElement('c:bubble3D');
        $this->writeAttribute('val', 0);
        $this->endElement();
    }

    /**
     * Write Data Labels
     *
     * @param Layout|null $chartLayout Chart layout
     *
     * @throws Exception
     */
    private function writeDataLabels(?Layout $chartLayout)
    {
        $this->startElement('c:dLbls');

        $this->startElement('c:showLegendKey');
        $showLegendKey = !$chartLayout ? 0 : $chartLayout->getShowLegendKey();
        $this->writeAttribute('val', $showLegendKey ? 1 : 0);
        $this->endElement();

        $this->startElement('c:showVal');
        $showVal = !$chartLayout ? 0 : $chartLayout->getShowVal();
        $this->writeAttribute('val', $showVal ? 1 : 0);
        $this->endElement();

        $this->startElement('c:showCatName');
        $showCatName = !$chartLayout ? 0 : $chartLayout->getShowCatName();
        $this->writeAttribute('val', $showCatName ? 1 : 0);
        $this->endElement();

        $this->startElement('c:showSerName');
        $showSerName = !$chartLayout ? 0 : $chartLayout->getShowSerName();
        $this->writeAttribute('val', $showSerName ? 1 : 0);
        $this->endElement();

        $this->startElement('c:showPercent');
        $showPercent = !$chartLayout ? 0 : $chartLayout->getShowPercent();
        $this->writeAttribute('val', $showPercent ? 1 : 0);
        $this->endElement();

        $this->startElement('c:showBubbleSize');
        $showBubbleSize = !$chartLayout ? 0 : $chartLayout->getShowBubbleSize();
        $this->writeAttribute('val', $showBubbleSize ? 1 : 0);
        $this->endElement();

        $this->startElement('c:showLeaderLines');
        $showLeaderLines = !$chartLayout ? 1 : $chartLayout->getShowLeaderLines();
        $this->writeAttribute('val', $showLeaderLines ? 1 : 0);
        $this->endElement();

        $this->endElement(); // c:dLbls
    }

    /**
     * Write Category Axis
     *
     * @param Title|null $axisTitle
     * @param string $chartType Chart type
     * @param string $id1
     * @param string $id2
     * @param boolean $isMultiLevelSeries
     * @param Axis $xAxis
     * @param Axis $yAxis
     */
    private function writeCategoryAxis(?Title $axisTitle, string $chartType, string $id1, string $id2, bool $isMultiLevelSeries, Axis $xAxis, Axis $yAxis)
    {
        $this->startElement('c:catAx');

        if ($id1 > 0) {
            $this->startElement('c:axId');
            $this->writeAttribute('val', $id1);
            $this->endElement();
        }

        $this->startElement('c:scaling');
        $this->startElement('c:orientation');
        $this->writeAttribute('val', $yAxis->getAxisOptionsProperty('orientation'));
        $this->endElement();
        $this->endElement();

        $this->startElement('c:delete');
        $this->writeAttribute('val', 0);
        $this->endElement();

        $this->startElement('c:axPos');
        $this->writeAttribute('val', "b");
        $this->endElement();

        $this->writeTitle($axisTitle, true);

        $this->startElement('c:numFmt');
        $this->writeAttribute('formatCode', $yAxis->getAxisNumberFormat());
        $this->writeAttribute('sourceLinked', $yAxis->getAxisNumberSourceLinked());
        $this->endElement();

        $this->startElement('c:majorTickMark');
        $this->writeAttribute('val', $yAxis->getAxisOptionsProperty('major_tick_mark'));
        $this->endElement();

        $this->startElement('c:minorTickMark');
        $this->writeAttribute('val', $yAxis->getAxisOptionsProperty('minor_tick_mark'));
        $this->endElement();

        $this->startElement('c:tickLblPos');
        $this->writeAttribute('val', $yAxis->getAxisOptionsProperty('axis_labels'));
        $this->endElement();

        if ($id2 > 0) {
            $this->startElement('c:crossAx');
            $this->writeAttribute('val', $id2);
            $this->endElement();

            $this->startElement('c:crosses');
            $this->writeAttribute('val', $yAxis->getAxisOptionsProperty('horizontal_crosses'));
            $this->endElement();
        }

        $this->startElement('c:auto');
        $this->writeAttribute('val', 1);
        $this->endElement();

        $this->startElement('c:lblAlgn');
        $this->writeAttribute('val', "ctr");
        $this->endElement();

        $this->startElement('c:lblOffset');
        $this->writeAttribute('val', 100);
        $this->endElement();

        if ($isMultiLevelSeries) {
            $this->startElement('c:noMultiLvlLbl');
            $this->writeAttribute('val', 0);
            $this->endElement();
        }
        $this->endElement();
    }

    /**
     * Write Value Axis
     *
     * @param Title|null $axisTitle
     * @param string $chartType Chart type
     * @param string $id1
     * @param string $id2
     * @param boolean $isMultiLevelSeries
     * @param Axis $xAxis
     * @param Axis $yAxis
     * @param GridLines $majorGridlines
     * @param GridLines $minorGridlines
     */
    private function writeValueAxis(?Title $axisTitle, string $chartType, string $id1, string $id2, bool $isMultiLevelSeries,
                                    Axis   $xAxis, Axis $yAxis, GridLines $majorGridlines, GridLines $minorGridlines)
    {
        $this->startElement('c:valAx');

        if ($id2) {
            $this->startElement('c:axId');
            $this->writeAttribute('val', $id2);
            $this->endElement();
        }

        $this->startElement('c:scaling');

        if ($value = $xAxis->getAxisOptionsProperty('maximum')) {
            $this->startElement('c:max');
            $this->writeAttribute('val', $value);
            $this->endElement();
        }

        if ($value = $xAxis->getAxisOptionsProperty('minimum')) {
            $this->startElement('c:min');
            $this->writeAttribute('val', $value);
            $this->endElement();
        }

        $this->startElement('c:orientation');
        $this->writeAttribute('val', $xAxis->getAxisOptionsProperty('orientation'));
        $this->endElement(); // c:orientation

        $this->endElement(); // c:scaling

        $this->startElement('c:delete');
        $this->writeAttribute('val', 0);
        $this->endElement();

        $this->startElement('c:axPos');
        $this->writeAttribute('val', "l");
        $this->endElement();

        $this->startElement('c:majorGridlines');
        $this->startElement('c:spPr');

        if ($majorGridlines->getLineColorProperty('value')) {
            $this->startElement('a:ln');
            $this->writeAttribute('w', $majorGridlines->getLineStyleProperty('width'));
            $this->startElement('a:solidFill');
            $this->startElement("a:{$majorGridlines->getLineColorProperty('type')}");
            $this->writeAttribute('val', $majorGridlines->getLineColorProperty('value'));
            $this->startElement('a:alpha');
            $this->writeAttribute('val', $majorGridlines->getLineColorProperty('alpha'));
            $this->endElement(); //end alpha
            $this->endElement(); //end srgbClr
            $this->endElement(); //end solidFill

            $this->startElement('a:prstDash');
            $this->writeAttribute('val', $majorGridlines->getLineStyleProperty('dash'));
            $this->endElement();

            if ($majorGridlines->getLineStyleProperty('join') === 'miter') {
                $this->startElement('a:miter');
                $this->writeAttribute('lim', '800000');
                $this->endElement();
            }
            else {
                $this->startElement('a:bevel');
                $this->endElement();
            }

            if ($value = $majorGridlines->getLineStyleProperty(['arrow', 'head', 'type'])) {
                $this->startElement('a:headEnd');
                $this->writeAttribute('type', $value);
                $this->writeAttribute('w', $majorGridlines->getLineStyleArrowParameters('head', 'w'));
                $this->writeAttribute('len', $majorGridlines->getLineStyleArrowParameters('head', 'len'));
                $this->endElement();
            }

            if ($value = $majorGridlines->getLineStyleProperty(['arrow', 'end', 'type'])) {
                $this->startElement('a:tailEnd');
                $this->writeAttribute('type', $value);
                $this->writeAttribute('w', $majorGridlines->getLineStyleArrowParameters('end', 'w'));
                $this->writeAttribute('len', $majorGridlines->getLineStyleArrowParameters('end', 'len'));
                $this->endElement();
            }
            $this->endElement(); //end ln
        }
        $this->startElement('a:effectLst');

        if ($majorGridlines->getGlowSize()) {
            $this->startElement('a:glow');
            $this->writeAttribute('rad', $majorGridlines->getGlowSize());
            $this->startElement("a:{$majorGridlines->getGlowColor('type')}");
            $this->writeAttribute('val', $majorGridlines->getGlowColor('value'));
            $this->startElement('a:alpha');
            $this->writeAttribute('val', $majorGridlines->getGlowColor('alpha'));
            $this->endElement(); //end alpha
            $this->endElement(); //end schemeClr
            $this->endElement(); //end glow
        }

        $this->writeShadowProperty($majorGridlines);

        if (null !== $majorGridlines->getSoftEdgesSize()) {
            $this->startElement('a:softEdge');
            $this->writeAttribute('rad', $majorGridlines->getSoftEdgesSize());
            $this->endElement(); //end softEdge
        }

        $this->endElement(); //end effectLst
        $this->endElement(); //end spPr
        $this->endElement(); //end majorGridLines

        if ($minorGridlines->getObjectState()) {
            $this->startElement('c:minorGridlines');
            $this->startElement('c:spPr');

            if (null !== $minorGridlines->getLineColorProperty('value')) {
                $this->startElement('a:ln');
                $this->writeAttribute('w', $minorGridlines->getLineStyleProperty('width'));
                $this->startElement('a:solidFill');
                $this->startElement("a:{$minorGridlines->getLineColorProperty('type')}");
                $this->writeAttribute('val', $minorGridlines->getLineColorProperty('value'));
                $this->startElement('a:alpha');
                $this->writeAttribute('val', $minorGridlines->getLineColorProperty('alpha'));
                $this->endElement(); //end alpha
                $this->endElement(); //end srgbClr
                $this->endElement(); //end solidFill

                $this->startElement('a:prstDash');
                $this->writeAttribute('val', $minorGridlines->getLineStyleProperty('dash'));
                $this->endElement();

                if ($minorGridlines->getLineStyleProperty('join') === 'miter') {
                    $this->startElement('a:miter');
                    $this->writeAttribute('lim', '800000');
                    $this->endElement();
                }
                else {
                    $this->startElement('a:bevel');
                    $this->endElement();
                }

                if ($value = $minorGridlines->getLineStyleProperty(['arrow', 'head', 'type'])) {
                    $this->startElement('a:headEnd');
                    $this->writeAttribute('type', $value);
                    $this->writeAttribute('w', $minorGridlines->getLineStyleArrowParameters('head', 'w'));
                    $this->writeAttribute('len', $minorGridlines->getLineStyleArrowParameters('head', 'len'));
                    $this->endElement();
                }

                if ($value = $minorGridlines->getLineStyleProperty(['arrow', 'end', 'type'])) {
                    $this->startElement('a:tailEnd');
                    $this->writeAttribute('type', $value);
                    $this->writeAttribute('w', $minorGridlines->getLineStyleArrowParameters('end', 'w'));
                    $this->writeAttribute('len', $minorGridlines->getLineStyleArrowParameters('end', 'len'));
                    $this->endElement();
                }
                $this->endElement(); //end ln
            }

            $this->startElement('a:effectLst');

            if (null !== $minorGridlines->getGlowSize()) {
                $this->startElement('a:glow');
                $this->writeAttribute('rad', $minorGridlines->getGlowSize());
                $this->startElement("a:{$minorGridlines->getGlowColor('type')}");
                $this->writeAttribute('val', $minorGridlines->getGlowColor('value'));
                $this->startElement('a:alpha');
                $this->writeAttribute('val', $minorGridlines->getGlowColor('alpha'));
                $this->endElement(); //end alpha
                $this->endElement(); //end schemeClr
                $this->endElement(); //end glow
            }

            $this->writeShadowProperty($minorGridlines);

            if (null !== $minorGridlines->getSoftEdgesSize()) {
                $this->startElement('a:softEdge');
                $this->writeAttribute('rad', $minorGridlines->getSoftEdgesSize());
                $this->endElement(); //end softEdge
            }

            $this->endElement(); //end effectLst
            $this->endElement(); //end spPr
            $this->endElement(); //end minorGridLines
        }

        $this->writeTitle($axisTitle, $chartType !== DataSeries::TYPE_BUBBLE_CHART);

        $this->startElement('c:numFmt');
        $this->writeAttribute('formatCode', $xAxis->getAxisNumberFormat());
        $this->writeAttribute('sourceLinked', $xAxis->getAxisNumberSourceLinked());
        $this->endElement();

        $this->startElement('c:majorTickMark');
        $this->writeAttribute('val', $xAxis->getAxisOptionsProperty('major_tick_mark'));
        $this->endElement();

        $this->startElement('c:minorTickMark');
        $this->writeAttribute('val', $xAxis->getAxisOptionsProperty('minor_tick_mark'));
        $this->endElement();

        $this->startElement('c:tickLblPos');
        $this->writeAttribute('val', $xAxis->getAxisOptionsProperty('axis_labels'));
        $this->endElement();

        $this->startElement('c:spPr');

        if ($xAxis->getFillProperty('value')) {
            $this->startElement('a:solidFill');
            $this->startElement("a:" . $xAxis->getFillProperty('type'));
            $this->writeAttribute('val', $xAxis->getFillProperty('value'));
            $this->startElement('a:alpha');
            $this->writeAttribute('val', $xAxis->getFillProperty('alpha'));
            $this->endElement();
            $this->endElement();
            $this->endElement();
        }

        $this->startElement('a:ln');

        $this->writeAttribute('w', $xAxis->getLineStyleProperty('width'));
        $this->writeAttribute('cap', $xAxis->getLineStyleProperty('cap'));
        $this->writeAttribute('cmpd', $xAxis->getLineStyleProperty('compound'));

        if ($xAxis->getLineProperty('value')) {
            $this->startElement('a:solidFill');
            $this->startElement("a:" . $xAxis->getLineProperty('type'));
            $this->writeAttribute('val', $xAxis->getLineProperty('value'));
            $this->startElement('a:alpha');
            $this->writeAttribute('val', $xAxis->getLineProperty('alpha'));
            $this->endElement();
            $this->endElement();
            $this->endElement();
        }

        $this->startElement('a:prstDash');
        $this->writeAttribute('val', $xAxis->getLineStyleProperty('dash'));
        $this->endElement();

        if ($xAxis->getLineStyleProperty('join') === 'miter') {
            $this->startElement('a:miter');
            $this->writeAttribute('lim', '800000');
            $this->endElement();
        }
        else {
            $this->startElement('a:bevel');
            $this->endElement();
        }

        if ($value = $xAxis->getLineStyleProperty(['arrow', 'head', 'type'])) {
            $this->startElement('a:headEnd');
            $this->writeAttribute('type', $value);
            $this->writeAttribute('w', $xAxis->getLineStyleArrowWidth('head'));
            $this->writeAttribute('len', $xAxis->getLineStyleArrowLength('head'));
            $this->endElement();
        }

        if ($value = $xAxis->getLineStyleProperty(['arrow', 'end', 'type'])) {
            $this->startElement('a:tailEnd');
            $this->writeAttribute('type', $value);
            $this->writeAttribute('w', $xAxis->getLineStyleArrowWidth('end'));
            $this->writeAttribute('len', $xAxis->getLineStyleArrowLength('end'));
            $this->endElement();
        }

        $this->endElement();

        $this->startElement('a:effectLst');

        if (null !== $xAxis->getGlowProperty('size')) {
            $this->startElement('a:glow');
            $this->writeAttribute('rad', $xAxis->getGlowProperty('size'));
            $this->startElement("a:{$xAxis->getGlowProperty(['color','type'])}");
            $this->writeAttribute('val', $xAxis->getGlowProperty(['color','value']));
            $this->startElement('a:alpha');
            $this->writeAttribute('val', $xAxis->getGlowProperty(['color','alpha']));
            $this->endElement();
            $this->endElement();
            $this->endElement();
        }

        $this->writeShadowProperty($xAxis);

        if (null !== $xAxis->getSoftEdgesSize()) {
            $this->startElement('a:softEdge');
            $this->writeAttribute('rad', $xAxis->getSoftEdgesSize());
            $this->endElement();
        }

        $this->endElement(); //effectList
        $this->endElement(); //end spPr

        if ($id1 > 0) {
            $this->startElement('c:crossAx');
            $this->writeAttribute('val', $id2);
            $this->endElement();

            if ($value = $xAxis->getAxisOptionsProperty('horizontal_crosses_value')) {
                $this->startElement('c:crossesAt');
                $this->writeAttribute('val', $value);
                $this->endElement();
            }
            else {
                $this->startElement('c:crosses');
                $this->writeAttribute('val', $xAxis->getAxisOptionsProperty('horizontal_crosses'));
                $this->endElement();
            }

            $this->startElement('c:crossBetween');
            $this->writeAttribute('val', "midCat");
            $this->endElement();

            if ($value = $xAxis->getAxisOptionsProperty('major_unit')) {
                $this->startElement('c:majorUnit');
                $this->writeAttribute('val', $value);
                $this->endElement();
            }

            if ($value = $xAxis->getAxisOptionsProperty('minor_unit')) {
                $this->startElement('c:minorUnit');
                $this->writeAttribute('val', $value);
                $this->endElement();
            }
        }

        if ($isMultiLevelSeries && $chartType !== DataSeries::TYPE_BUBBLE_CHART) {
            $this->startElement('c:noMultiLvlLbl');
            $this->writeAttribute('val', 0);
            $this->endElement();
        }

        $this->endElement();
    }

    /**
     * @param $obj
     *
     * @return void
     */
    private function writeShadowProperty($obj)
    {
        if ($obj->getShadowProperty('presets')) {
            $this->startElement("a:{$obj->getShadowProperty('effect')}");

            if ($value = $obj->getShadowProperty('blur')) {
                $this->writeAttribute('blurRad', $value);
            }
            if ($value = $obj->getShadowProperty('distance')) {
                $this->writeAttribute('dist', $value);
            }
            if ($value = $obj->getShadowProperty('direction')) {
                $this->writeAttribute('dir', $value);
            }
            if ($value = $obj->getShadowProperty('algn')) {
                $this->writeAttribute('algn', $value);
            }
            if ($value = $obj->getShadowProperty(['size','sx'])) {
                $this->writeAttribute('sx', $value);
            }
            if ($value = $obj->getShadowProperty(['size','sy'])) {
                $this->writeAttribute('sy', $value);
            }
            if ($value = $obj->getShadowProperty(['size','kx'])) {
                $this->writeAttribute('kx', $value);
            }
            if ($value = $obj->getShadowProperty('rotWithShape')) {
                $this->writeAttribute('rotWithShape', $value);
            }

            $this->startElement("a:{$obj->getShadowProperty(['color','type'])}");
            $this->writeAttribute('val', $obj->getShadowProperty(['color','value']));
            $this->startElement('a:alpha');
            $this->writeAttribute('val', $obj->getShadowProperty(['color','alpha']));
            $this->endElement();
            $this->endElement();

            $this->endElement();
        }
    }
}