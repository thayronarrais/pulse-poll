import './echo';

import Alpine from 'alpinejs';
import { liveVoter } from './live/voter';
import { liveControl } from './live/control';
import { shareLink } from './share-link';

window.Alpine = Alpine;

Alpine.data('liveVoter', liveVoter);
Alpine.data('liveControl', liveControl);
Alpine.data('shareLink', shareLink);

Alpine.start();
