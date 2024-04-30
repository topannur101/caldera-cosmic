import './bootstrap';

import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';
import ApexCharts from 'apexcharts';
import axios from 'axios';

const notyf = new Notyf({
   duration: 5000,
   position: {
      x:'center',
      y:'top',
   }
});

const escKey = new KeyboardEvent('keydown', {
   key: 'Escape',
   keyCode: 27,
   which: 27,
   code: 'Escape',
});

window.notyf = notyf;
window.escKey = escKey;
window.ApexCharts = ApexCharts;
window.axios = axios;

