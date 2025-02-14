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

// Add this namespace management at the top of your application (e.g., in app.js)
window.AppWebSockets = {
   // Store websocket instances with their identifiers
   connections: {},
   
   // Helper to get or create a websocket connection
   getOrCreate(id, url) {
       if (this.connections[id]?.readyState === WebSocket.OPEN) {
           console.log(`Reusing existing WebSocket: ${id}`);
           return this.connections[id];
       }
       
       // Close existing connection if in bad state
       if (this.connections[id]) {
           console.log(`Closing old WebSocket: ${id}`);
           this.connections[id].close();
       }
       
       console.log(`Creating new WebSocket: ${id}`);
       const ws = new WebSocket(url);
       this.connections[id] = ws;
       
       ws.onclose = () => {
           console.log(`WebSocket closed: ${id}`);
           if (this.connections[id] === ws) {
               delete this.connections[id];
           }
       };
       
       return ws;
   }
};