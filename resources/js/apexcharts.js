import ApexCharts from 'apexcharts';
import Chart from 'chart.js/auto';
import annotationPlugin from 'chartjs-plugin-annotation';
import datalabelsPlugin from 'chartjs-plugin-datalabels';
import zoomPlugin from 'chartjs-plugin-zoom';
import 'chartjs-adapter-date-fns';

Chart.register(annotationPlugin);
Chart.register(datalabelsPlugin);
Chart.register(zoomPlugin);

window.ApexCharts = ApexCharts;
window.Chart = Chart;