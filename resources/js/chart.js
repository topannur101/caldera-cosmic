import Chart from 'chart.js/auto';
import annotationPlugin from 'chartjs-plugin-annotation';
import datalabelsPlugin from 'chartjs-plugin-datalabels';
import 'chartjs-adapter-date-fns';
Chart.register(annotationPlugin);
Chart.register(datalabelsPlugin);
window.Chart = Chart;

