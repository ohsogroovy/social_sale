import React, {
    createContext,
    useContext,
    useEffect,
    useRef,
    useState,
} from "react";
import axios from "axios";
import { notifications } from "@mantine/notifications";

const AppContext = createContext();
export const AppProvider = ({ children }) => {
    const [searchQuery, setSearchQuery] = useState("");
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState("");
    const commentSubmitBtnRef = useRef(null);

    useEffect(() => {
        const fetchUserSettings = async () => {
            try {
                const response = await axios.get("/user");
                const { auto_trigger } = response.data.user;
                const autoTagToggle = document.getElementById("autoTagToggle");
                if (autoTagToggle) {
                    autoTagToggle.checked = auto_trigger;
                }
            } catch (error) {
                console.error("Error fetching user settings:", error);
            }
        };

        fetchUserSettings();

        const handleSearchProduct = (event) => {
            const query = event.detail.query;
            setSearchQuery(query);
        };

        const handleAutoTagToggle = async (event) => {
            const enabled = event.detail.enabled;
            console.log("autoTagToggle received:", enabled);

            try {
                await axios.post("/auto-trigger", { auto_trigger: enabled });
                notifications.show({
                    title: "Auto Tagging",
                    message: `Auto Tagging has been ${
                        enabled ? "enabled" : "disabled"
                    }.`,
                    color: "green",
                });
            } catch (error) {
                console.error("Error toggling auto-tagging:", error);
                notifications.show({
                    title: "Error",
                    message: "Failed to toggle Auto Tagging.",
                    color: "red",
                });
            }
        };

        const handleKeyPress = (event) => {
            if (event.key === "Enter") {
                setTimeout(() => {
                    console.log("searchProduct by enter", event.target.value);
                    searchProduct(event.target.value);
                }, 800);
            }
        };

        window.addEventListener("searchProduct", handleSearchProduct);
        window.addEventListener("autoTagToggle", handleAutoTagToggle);

        const inputElement = document.getElementById("searchQuery");
        inputElement.addEventListener("keydown", handleKeyPress);

        return () => {
            window.removeEventListener("searchProduct", handleSearchProduct);
            window.removeEventListener("autoTagToggle", handleAutoTagToggle);
            inputElement.removeEventListener("keydown", handleKeyPress);
        };
    }, [searchQuery]);

    const searchProduct = async (query) => {
        console.log("searchProduct", query);

        if (query.trim() === "") {
            return;
        }

        setLoading(true);
        try {
            const res = await axios.get("/search-product", {
                params: { sku: query },
            });

            const { message, data, autoTrigger } = res.data;

            if (message === "This product does not have any tags.") {
                notifications.show({
                    title: "No Tags",
                    message: message,
                    color: "yellow",
                    position: "top-left",
                });
            } else {
                if (data?.shortestTag) {
                    notifications.show({
                        title: "Tag: " + data.shortestTag,
                        position: "top-left",
                    });
                }
                if (autoTrigger) {
                    // First close any existing auto-trigger notifications
                    notifications.hide("auto-trigger-notification");

                    // Then show the new notification
                    setTimeout(() => {
                        notifications.show({
                            message: `"${autoTrigger?.productName}" (SKU: ${autoTrigger?.sku}) has been assigned trigger "${autoTrigger?.triggerTag}". Inventory: ${autoTrigger?.quantity}`,
                            color: "green",
                            position: "top-left",
                            autoClose: 30000, // Stay open for 30 seconds
                            id: "auto-trigger-notification", // Unique ID to identify this notification type
                        });
                    }, 100); // Small delay to ensure close happens first
                }
                notifications.show({
                    title: "Product SKU Found! It has been copied to clipboard.",
                    color: "green",
                });
                setMessage(message);
                setTimeout(() => {
                    if (commentSubmitBtnRef?.current) {
                        commentSubmitBtnRef.current.click();
                        document.getElementById("searchQuery")?.focus();
                    }
                }, 400);

                try {
                    await navigator.clipboard.writeText(message);
                } catch (err) {
                    notifications.show({
                        title: "Clipboard Copy Failed",
                        message:
                            "Failed to copy to clipboard. Please copy manually.",
                        color: "orange",
                    });
                    prompt("Copy this product message manually:", message);
                }
            }

            // Dispatch event for Tags component to reload
            window.dispatchEvent(new Event("tags-update"));
        } catch (error) {
            console.log(error);
            if (error.response && error.response.status === 404) {
                notifications.show({
                    title: "Product Not Found",
                    message: "No product found with this SKU.",
                    color: "red",
                });
            } else if (error.name !== "CanceledError") {
                notifications.show({
                    title: "Error",
                    message:
                        "An error occurred while searching for the product.",
                    color: "red",
                });
                setSearchQuery("");
            }
        } finally {
            const resetEvent = new CustomEvent("resetSearchField");
            window.dispatchEvent(resetEvent);
            setLoading(false);
        }
    };

    return (
        <AppContext.Provider value={{ message, commentSubmitBtnRef }}>
            {children}
        </AppContext.Provider>
    );
};

export const useAppContext = () => {
    return useContext(AppContext);
};
