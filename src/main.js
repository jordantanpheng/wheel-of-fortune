import Vue from 'vue';
import VueRouter from 'vue-router'
import VueCookies from 'vue-cookies'
import App from './App.vue';
import { BootstrapVue, IconsPlugin } from 'bootstrap-vue'
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue/dist/bootstrap-vue.css'
import VueResource from "vue-resource";

Vue.use(VueRouter)
Vue.use(VueCookies)
Vue.$cookies.config('7d')
Vue.use(BootstrapVue)
Vue.use(IconsPlugin)
Vue.use(VueResource);
Vue.http.options.root = '/api';

new VueRouter({
    mode: "history",
    routes: [
        { path: '/', component: App },
        { path: '*', redirect: '/'  }
    ]
});

new Vue({
  render: h => h(App),
}).$mount('#app');

