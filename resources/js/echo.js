// filepath: resources/js/echo.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: '1fcedc560b41371faf48', // Replace with your Pusher key
    cluster: 'c81e728d9d4c2f636f067f89cc14862c', // Replace with your Pusher cluster
    forceTLS: true,
});