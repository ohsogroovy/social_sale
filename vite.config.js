import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.jsx"],
            refresh: true,
        }),
    ],
    define: {
        "process.env": {
            PUSHER_APP_KEY: JSON.stringify(process.env.PUSHER_APP_KEY),
            PUSHER_APP_CLUSTER: JSON.stringify(process.env.PUSHER_APP_CLUSTER),
            PUSHER_APP_ID: JSON.stringify(process.env.PUSHER_APP_ID),
        },
    },
});
