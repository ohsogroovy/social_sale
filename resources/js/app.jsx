import "./bootstrap";
import Alpine from "alpinejs";

import React from "react";
import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { MantineProvider } from "@mantine/core";
import { Notifications } from "@mantine/notifications";
import "@mantine/notifications/styles.css";
import { AppProvider } from "./AppContext";

window.Alpine = Alpine;

Alpine.start();
try {
    console.log("Initializing Inertia App");
    createInertiaApp({
        id: "app",
        resolve: (name) => {
            console.log("name: ", name);
            const pages = import.meta.glob("./Pages/**/*.jsx", { eager: true });
            return pages[`./Pages/${name}.jsx`];
        },
        setup({ el, App, props }) {
            createRoot(el).render(
                <AppProvider>
                    <MantineProvider theme={{ colorScheme: "dark" }}>
                        <Notifications
                            position="bottom-center"
                            autoClose={2000}
                        />

                        <App {...props} />
                    </MantineProvider>
                </AppProvider>
            );
        },
    });
} catch (error) {
    console.log("error in init inertia: ", error);
}
