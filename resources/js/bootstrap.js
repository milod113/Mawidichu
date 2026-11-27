import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Laravel Echo + Reverb (WebSockets locaux, sans Internet). Les canaux
 * temps réel (file d'attente, écran TV…) seront branchés aux étapes 4 à 6.
 */
import './echo';
