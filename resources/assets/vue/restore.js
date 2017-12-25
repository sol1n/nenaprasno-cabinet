window.Vue = require('vue');
window.axios = require('axios');

// window.axios.defaults.headers.common = {
//     'X-CSRF-TOKEN': window.Laravel.csrfToken
// };

// Vue.component('example', './components/restore/Example.vue');

import Restore from './components/restore/Root.vue';

const app = new Vue({
    el: '#restore',
    components: {
        Restore
    }
});