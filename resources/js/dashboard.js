import { createApp } from 'vue';
import App from './dashboard/App.vue';

const mountPoint = document.getElementById('dashboard-app');
const bootstrapData = JSON.parse(document.getElementById('dashboard-bootstrap').textContent);

createApp(App, { bootstrap: bootstrapData }).mount(mountPoint);
