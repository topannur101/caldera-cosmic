import './bootstrap';
import axios from 'axios';

function calderaSetTheme()
{
   if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
}

const escKey = new KeyboardEvent('keydown', {
   key: 'Escape',
   keyCode: 27,
   which: 27,
   code: 'Escape',
});

calderaSetTheme()
window.calderaSetTheme  = calderaSetTheme;
window.axios            = axios;
window.escKey           = escKey;

window.toast = function(message, options = {}) {
   let description = options.description || '';
   let type = options.type || 'default';
   let position = options.position || 'top-center';
   let html = options.html || '';

   window.dispatchEvent(new CustomEvent('toast-show', { 
       detail: { type, message, description, position, html }
   }));
};