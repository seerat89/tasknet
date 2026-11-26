import { App } from '@modelcontextprotocol/ext-apps';
import { startApp } from './host';
import './styles.css';

const app = new App({ name: 'TaskNet', version: '1.0.0' }, { availableDisplayModes: ['inline', 'fullscreen'] });

void startApp(app, document.getElementById('app')!);
