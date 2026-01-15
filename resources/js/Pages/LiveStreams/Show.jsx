import { usePage } from "@inertiajs/react";
import React, { useEffect, useState, useRef } from "react";
import "@mantine/core/styles.css";
import { Badge, Card, Button, TextInput } from "@mantine/core";
import echo from "../../echo";
import axios from "axios";
import { notifications } from "@mantine/notifications";
import debounce from "lodash.debounce";
import { useAppContext } from "../../AppContext";

const Show = () => {
    const { message, commentSubmitBtnRef } = useAppContext();
    const [comments, setComments] = useState([]);
    const [newComment, setNewComment] = useState("");
    const [sendMessageLoading, setSendMessageLoading] = useState(false);
    const [hasMore, setHasMore] = useState(true);
    const commentsEndRef = useRef(null);
    const { props } = usePage();
    const [currentLiveStreams, setCurrentLiveStreams] = useState(props.current_live_streams);
    const [isLive, setIsLive] = useState(false);
    const [livestreamDetected, setLivestreamDetected] = useState(() => {
        // Check if we've already detected a livestream in this session
        return localStorage.getItem("livestreamDetected") === "true";
    });
    const [showBanner, setShowBanner] = useState(false);

    // Manual sync button state
    const [syncLoading, setSyncLoading] = useState(false);
    const [syncTimer, setSyncTimer] = useState(10);
    const [canSync, setCanSync] = useState(false);
    const timerRef = useRef(null);

    // Timer effect for manual sync button
    useEffect(() => {
        if (syncTimer > 0) {
            timerRef.current = setTimeout(() => {
                setSyncTimer((prev) => prev - 1);
            }, 1000);
        } else {
            setCanSync(true);
        }

        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
        };
    }, [syncTimer]);

    useEffect(() => {
        const fetchComments = async () => {
            try {
                const res = await axios.get(`/latest-comments`);
                if (res.data && res.data.data && res.data.data.comments) {
                    const fetchedComments = res.data.data.comments;
                    setComments(fetchedComments);
                }
            } catch (error) {
                console.log("Error fetching comments:", error);
            }
        };

        if (currentLiveStreams && currentLiveStreams?.is_live) {
            fetchComments();
            const interval = setInterval(fetchComments, 5000);

            return () => clearInterval(interval);
        }
    }, [currentLiveStreams]);

    useEffect(() => {
        if (message) {
            setNewComment(message);
        }
    }, [message]);

    useEffect(() => {
        const handleCommentPosted = debounce((event) => {
            setComments((prevComments) => {
                const updatedComments = [...prevComments, event.comment];
                return updatedComments;
            });
        }, 100);

        echo.channel("my-channel").listen(
            ".comment.posted",
            handleCommentPosted
        );

        return () => {
            echo.channel("my-channel").stopListening(
                ".comment.posted",
                handleCommentPosted
            );
        };
    }, []);

    useEffect(() => {
        const handleStreamUpdate = (event) => {
            const newIsLiveState = event.post.is_live;
            setIsLive(newIsLiveState);

            // If the livestream ends, reset the detection state
            if (!newIsLiveState) {
                localStorage.removeItem("livestreamDetected");
                setLivestreamDetected(false);
            }
        };

        echo.channel("live-stream").listen(
            ".stream.update",
            handleStreamUpdate
        );

        return () => {
            echo.channel("live-stream").stopListening(
                ".stream.update",
                handleStreamUpdate
            );
        };
    }, []);

    useEffect(() => {
        if (currentLiveStreams) {
            const newIsLiveState = currentLiveStreams?.is_live;
            setIsLive(newIsLiveState);
            setComments(currentLiveStreams?.comments);

            // If the livestream ends, reset the detection state
            if (!newIsLiveState) {
                localStorage.removeItem("livestreamDetected");
                setLivestreamDetected(false);
            }
        }
    }, [currentLiveStreams]);

    // Effect for handling livestream detection and page reload

    // Effect for handling livestream detection notification and page reload
    useEffect(() => {
        if (isLive && !livestreamDetected) {
            // Set the state and save to localStorage to persist across reloads
            setLivestreamDetected(true);
            localStorage.setItem("livestreamDetected", "true");

            // Show the banner
            setShowBanner(true);

            // Force a hard reload after 1 second
            setTimeout(() => {
                const url = new URL(window.location.href);

                window.location.href = url.toString(); // Triggers full reload with cache-busting
            }, 2000); // 1 second delay
        }
    }, [isLive, livestreamDetected]);

    useEffect(() => {
        if (
            commentsEndRef.current &&
            commentsEndRef.current.getBoundingClientRect().top <
                window.innerHeight
        ) {
            commentsEndRef.current.scrollIntoView({
                behavior: "smooth",
                block: "nearest",
                inline: "start",
            });
        }
    }, [comments]);

    const handleAddComment = async (e) => {
        e.preventDefault();
        setSendMessageLoading(true);
        try {
            const res = await axios.post(
                `/post-comment/${currentLiveStreams?.id}`,
                {
                    message: newComment,
                }
            );
            if (!res.data.error) {
                setNewComment("");
            } else {
                notifications.show({
                    title: "Error",
                    message: res.data.message,
                    color: "red",
                });
            }
        } catch (error) {
            notifications.show({
                title: "Error",
                message: "An error occurred while posting the comment.",
                color: "red",
            });
        } finally {
            setSendMessageLoading(false);
        }
    };

    const handleManualSync = async () => {
        setSyncLoading(true);
        try {
            const res = await axios.get('/sync-manual-live-stream');
            
            if (!res.data.error) {
                // Update the current live streams state with the fetched data
                const liveStreamData = res.data.data.live_stream;
                setCurrentLiveStreams(liveStreamData);
                
                // Update other related states
                setIsLive(true);
                setLivestreamDetected(true);
                localStorage.setItem("livestreamDetected", "true");
                
                // Update comments if available
                if (liveStreamData.comments) {
                    setComments(liveStreamData.comments);
                }
                
                notifications.show({
                    title: "Success",
                    message: "Live stream found and synced!",
                    color: "green",
                });
            } else {
                notifications.show({
                    title: "No Live Stream",
                    message: res.data.message || "No live stream detected",
                    color: "yellow",
                });
            }
        } catch (error) {
            if (error.response?.status === 404) {
                notifications.show({
                    title: "No Live Stream",
                    message: "No live stream detected",
                    color: "yellow",
                });
            } else {
                notifications.show({
                    title: "Error",
                    message: "Failed to sync live stream",
                    color: "red",
                });
            }
        } finally {
            setSyncLoading(false);
            // Reset timer for next sync
            setSyncTimer(10);
            setCanSync(false);
        }
    };

    const handleSync = async () => {
        setSyncLoading(true);
        setCanSync(false);
        setSyncTimer(10);

        try {
            const res = await axios.get(`/latest-comments`);
            if (res.data && res.data.data && res.data.data.comments) {
                const fetchedComments = res.data.data.comments;
                setComments(fetchedComments);

                notifications.show({
                    title: "Sync Successful",
                    message: "Comments synced successfully.",
                    color: "green",
                });
            }
        } catch (error) {
            notifications.show({
                title: "Error",
                message: "An error occurred while syncing comments.",
                color: "red",
            });
        } finally {
            setSyncLoading(false);
        }
    };

    return (
        <>
            {showBanner ? (
                <div className="flex flex-col items-center justify-center h-screen">
                    <div className="px-4 py-3 rounded mb-4 max-w-md w-full">
                        <div className="flex flex-col items-center mb-3">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-black mb-3"></div>
                        </div>

                        <div className="text-center mt-2 ">
                            Live stream detected and connecting...
                        </div>
                    </div>
                </div>
            ) : isLive && livestreamDetected ? (
                <div className="py-12 px-2 md:px-5 flex w-full justify-center items-center">
                    <Card
                        withBorder
                        shadow="sm"
                        radius="md"
                        maw={600}
                        className="w-full"
                    >
                        <Card.Section withBorder py="md" px="lg" mb={16}>
                            <div className="flex justify-between items-center">
                                <div className="flex flex-row items-center gap-4">
                                    <h2 className="text-xl font-bold">
                                        Live Stream
                                    </h2>{" "}
                                    <Badge color="green">Live</Badge>
                                </div>
                                <Button
                                    onClick={handleManualSync}
                                    disabled={!canSync}
                                    loading={syncLoading}
                                    size="sm"
                                    variant="light"
                                >
                                    {syncLoading 
                                        ? "Syncing..." 
                                        : canSync 
                                            ? "Refresh" 
                                            : `Refresh (${syncTimer}s)`
                                    }
                                </Button>
                            </div>
                        </Card.Section>
                        <div
                            // onScroll={handleScroll}
                            className="flex-1 justify-end overflow-y-auto mb-4 space-y-2"
                            style={{ minHeight: "400px", maxHeight: "500px" }}
                        >
                            {comments.map((comment) => (
                                <div
                                    key={comment.id}
                                    className="p-2 border rounded bg-gray-100 flex justify-between items-end"
                                >
                                    <div>
                                        <div className="text-sm font-bold">
                                            {comment.commenter}
                                        </div>
                                        <div className="text-sm">
                                            {comment.message}
                                        </div>
                                    </div>
                                    <div className="text-xs text-gray-500">
                                        {new Date(
                                            comment.facebook_created_at
                                        ).toLocaleString()}
                                    </div>
                                </div>
                            ))}
                            <div ref={commentsEndRef} />
                        </div>
                        <form
                            onSubmit={handleAddComment}
                            className="flex items-center"
                        >
                            <TextInput
                                value={newComment}
                                onChange={(e) => setNewComment(e.target.value)}
                                placeholder="Type a comment..."
                                className="flex-1 mr-2"
                                size="sm"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                ref={commentSubmitBtnRef}
                                loading={sendMessageLoading}
                            >
                                Send
                            </Button>
                        </form>
                        <div className="flex justify-end mt-4">
                            <Button
                                onClick={handleSync}
                                loading={syncLoading}
                                disabled={!canSync}
                                size="sm"
                                variant="outline"
                            >
                                {syncLoading
                                    ? "Syncing..."
                                    : canSync
                                    ? "Sync Comments"
                                    : `Wait ${syncTimer}s`}
                            </Button>
                        </div>
                    </Card>
                </div>
            ) : (
                <div
                    className="text-center text-xl font-bold flex flex-col justify-center items-center italic"
                    style={{ height: "80vh", textDecoration: "italic" }}
                >
                    <div className="mb-6">No Live Stream Found</div>
                    <div className="flex flex-col items-center gap-4">
                        <p className="text-sm text-gray-600 max-w-md">
                            Sometimes live streams may not be detected automatically. 
                            Use the manual sync button below to check for live streams.
                        </p>
                        <Button
                            onClick={handleManualSync}
                            disabled={!canSync}
                            loading={syncLoading}
                            size="md"
                            variant="outline"
                        >
                            {syncLoading 
                                ? "Syncing..." 
                                : canSync 
                                    ? "Manual Sync Live Stream" 
                                    : `Manual Sync (${syncTimer}s)`
                            }
                        </Button>
                    </div>
                </div>
            )}
        </>
    );
};

export default Show;
