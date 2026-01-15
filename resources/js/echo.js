import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

console.log("echo init");
const echo = new Echo({
    broadcaster: "pusher",
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    secret: import.meta.env.VITE_PUSHER_APP_SECRET,
    app_id: import.meta.env.VITE_PUSHER_APP_ID,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    // encrypted: true,
});

export default echo;
