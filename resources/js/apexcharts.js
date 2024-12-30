import ApexCharts from 'apexcharts';
import Chart from 'chart.js/auto';
import annotationPlugin from 'chartjs-plugin-annotation';
import 'chartjs-adapter-date-fns';

Chart.register(annotationPlugin);

window.ApexCharts = ApexCharts;
window.Chart = Chart;