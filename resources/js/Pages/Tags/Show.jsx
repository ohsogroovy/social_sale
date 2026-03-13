import { usePage } from "@inertiajs/react";
import React, { useState, useEffect, useRef } from "react";
import axios from "axios";
import {
    Card,
    Group,
    Text,
    TextInput,
    Pagination,
    ActionIcon,
    Badge,
    Button,
    ScrollArea,
} from "@mantine/core";
import { IconSearch, IconX } from "@tabler/icons-react";
import { notifications } from "@mantine/notifications";

const ShowComments = () => {
    const { post } = usePage().props;
    const { comments: initialComments } = usePage().props;
    const [searchQuery, setSearchQuery] = useState("");
    const [comments, setComments] = useState(initialComments);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);
    const abortControllerRef = useRef(null);
    const [isSearch, setIsSearch] = useState(false);
    const [loadingCsv, setLoadingCsv] = useState(false);

    useEffect(() => {
        setComments(initialComments);
        const searchParams = new URLSearchParams(window.location.search);
        const query = searchParams.get("search") || "";
        setSearchQuery(query);
    }, [initialComments]);

    useEffect(() => {
        const abortController = new AbortController();
        abortControllerRef.current = abortController;

        const fetchComments = async () => {
            setLoading(true);
            try {
                if (searchQuery) {
                    setIsSearch(true);
                    const response = await axios.get(
                        `/search-comments/${post.id}?page=${page}`,
                        {
                            params: {
                                search: searchQuery,
                            },
                            signal: abortController.signal,
                        }
                    );
                    setComments(response.data.comments);

                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.set("search", searchQuery);
                    window.history.replaceState(null, "", newUrl);
                } else {
                    setIsSearch(false);
                    setComments(initialComments);

                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.delete("search");
                    window.history.replaceState(null, "", newUrl);
                }
            } catch (error) {
                console.log(error);
                if (error.name !== "CanceledError") {
                    console.error("Error fetching comments:", error);
                }
            } finally {
                setLoading(false);
            }
        };

        fetchComments();

        return () => abortController.abort();
    }, [searchQuery, page, post.id, initialComments]);

    if (!post) {
        return <div>Error loading data</div>;
    }

    const downloadCsv = async () => {
        setLoadingCsv(true);
        try {
            const response = await axios.get(`/import-csv/${post.id}`, {
                responseType: "blob",
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement("a");
            link.href = url;

            const contentDisposition = response.headers["content-disposition"];
            const fileNameMatch = contentDisposition?.match(/filename="(.+)"/);
            const fileName = fileNameMatch
                ? fileNameMatch[1]
                : `comments_${post.id}.csv`;

            link.setAttribute("download", fileName);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
            notifications.show({
                color: "green",
                message: "CSV downloaded successfully",
            });
        } catch (error) {
            notifications.show({
                message: "Error downloading CSV",
                color: "red",
            });
            console.error("Error downloading CSV:", error);
        } finally {
            setLoadingCsv(false);
        }
    };

    const icon = (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18px"
            height="18px"
            viewBox="0 0 24 24"
            fill="none"
        >
            <path
                d="M12 3V16M12 16L16 11.625M12 16L8 11.625"
                stroke="#ffffff"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M15 21H9C6.17157 21 4.75736 21 3.87868 20.1213C3 19.2426 3 17.8284 3 15M21 15C21 17.8284 21 19.2426 20.1213 20.1213C19.8215 20.4211 19.4594 20.6186 19 20.7487"
                stroke="#ffffff"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );

    return (
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
                        <div className="flex flex-col">
                            <div className="text-sm">Post Details :</div>
                            <Text size="lg" fw={500}>
                                {post.message}
                            </Text>
                            <Text size="sm" color="dimmed">
                                {new Date(post.created_at).toLocaleString()}
                            </Text>
                        </div>
                        <Button
                            loading={loadingCsv}
                            leftSection={icon}
                            onClick={downloadCsv}
                            size="sm"
                        >
                            Download Csv
                        </Button>
                    </div>
                    <div className="mt-4">
                        <TextInput
                            placeholder="Search comments..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            rightSection={
                                loading && (
                                    <ActionIcon
                                        size="lg"
                                        loading={loading}
                                        variant="transparent"
                                    >
                                        <IconSearch stroke={2} />
                                    </ActionIcon>
                                )
                            }
                            leftSection={
                                searchQuery && (
                                    <ActionIcon
                                        size="xs"
                                        variant="transparent"
                                        color="gray"
                                        onClick={() => setSearchQuery("")}
                                    >
                                        <IconX stroke={1} />
                                    </ActionIcon>
                                )
                            }
                        />
                    </div>
                </Card.Section>
                <ScrollArea h={500} scrollbarSize={2}>
                    <div className="space-y-2">
                        {comments && comments.data.length > 0 ? (
                            comments.data.map((comment) => (
                                <a
                                    href={comment.post_link}
                                    target="_blank"
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
                                    <div className="text-xs text-gray-500 flex flex-col items-end gap-1">
                                        <div>
                                            {comment?.private_message ? (
                                                <Badge color="green" size="xs">
                                                    Message Sent
                                                </Badge>
                                            ) : (
                                                <Badge color="gray" size="xs">
                                                    No Tag Found
                                                </Badge>
                                            )}
                                        </div>
                                        <div>
                                            {new Date(
                                                comment.facebook_created_at
                                            ).toLocaleString()}
                                        </div>
                                    </div>
                                </a>
                            ))
                        ) : (
                            <Text size="sm" color="dimmed">
                                No comments available
                            </Text>
                        )}
                    </div>
                </ScrollArea>
                {comments && comments.data.length > 0 && (
                    <div className="flex justify-center mt-5">
                        <Pagination.Root
                            total={comments.last_page}
                            value={comments.current_page}
                            onChange={(p) => setPage(p)}
                            getItemProps={(page) => ({
                                component: "a",
                                href: !isSearch
                                    ? `/show-post/comment/${post.id}?page=${page}`
                                    : undefined,
                            })}
                        >
                            <Group gap={7} mt="xl">
                                <Pagination.First
                                    component="a"
                                    href={
                                        !isSearch
                                            ? `${comments.first_page_url}`
                                            : undefined
                                    }
                                />
                                <Pagination.Previous
                                    component="a"
                                    href={
                                        !isSearch
                                            ? `${comments.prev_page_url}`
                                            : undefined
                                    }
                                />
                                <Pagination.Items />
                                <Pagination.Next
                                    component="a"
                                    href={
                                        !isSearch
                                            ? `${comments.next_page_url}`
                                            : undefined
                                    }
                                />
                                <Pagination.Last
                                    component="a"
                                    href={
                                        !isSearch
                                            ? `${comments.last_page_url}`
                                            : undefined
                                    }
                                />
                            </Group>
                        </Pagination.Root>
                    </div>
                )}
            </Card>
        </div>
    );
};

export default ShowComments;
