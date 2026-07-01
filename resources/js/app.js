import './echo';

import Alpine from 'alpinejs';
import { liveVoter } from './live/voter';
import { liveControl } from './live/control';

window.Alpine = Alpine;

Alpine.data('liveVoter', liveVoter);
Alpine.data('liveControl', liveControl);

Alpine.start();
