import React from 'react';
import { createRoot } from 'react-dom/client';
import EuroCrossDashboard from './components/EuroCrossDashboard.jsx';
import '../css/app.css';

const mountElement = document.getElementById('app');

if (mountElement) {
    createRoot(mountElement).render(
        React.createElement(React.StrictMode, null, React.createElement(EuroCrossDashboard)),
    );
} else {
    console.warn('[EuroCross] Mount element #app was not found; dashboard was not mounted.');
}
