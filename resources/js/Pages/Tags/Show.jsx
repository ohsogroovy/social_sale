import { usePage, router } from "@inertiajs/react";
import React, { useState, useEffect } from "react";
import "@mantine/core/styles.css";
import {
    Card,
    Group,
    Table,
    Text,
    Checkbox,
    Button,
    Pagination,
} from "@mantine/core";
import axios from "axios";

const Show = () => {
    const { props } = usePage();

    const [tags, settags] = useState(props.tags);
    const [selectedTags, setSelectedTags] = useState([]);
    const [checkingTags, setCheckingTags] = useState(false);
    useEffect(() => {
        settags(props.tags);
    }, [props.tags]);
    useEffect(() => {
        const handleTagsUpdate = () => {
            router.reload();
        };

        window.addEventListener("tags-update", handleTagsUpdate);
        return () =>
            window.removeEventListener("tags-update", handleTagsUpdate);
    }, []);

    const handleSelectAll = (checked) => {
        setSelectedTags(checked ? tags.data.map((tag) => tag.id) : []);
    };

    const handleSelectTag = (tagId) => {
        setSelectedTags((prev) =>
            prev.includes(tagId)
                ? prev.filter((id) => id !== tagId)
                : [...prev, tagId]
        );
    };

    const handleDelete = () => {
        if (!selectedTags.length) return;

        if (confirm("Are you sure you want to delete selected tags?")) {
            axios
                .delete("/generated-tags", {
                    data: { tag_ids: selectedTags },
                })
                .then(() => {
                    setSelectedTags([]);
                    router.reload();
                })
                .catch((error) => {
                    console.error("Error deleting tags:", error);
                });
        }
    };

    const handleDeleteAll = () => {
        if (confirm("Are you sure you want to delete ALL tags?")) {
            axios
                .delete("/generated-tags/all")
                .then(() => {
                    setSelectedTags([]);
                    setCheckingTags(true);
                    // Trigger an immediate reload to start the check process
                    setTimeout(() => {
                        router.reload();
                    }, 1000);
                })
                .catch((error) => {
                    console.error("Error deleting all tags:", error);
                });
        }
    };

    // Effect to check if tags exist after page reload
    useEffect(() => {
        // When checking tags is active
        if (checkingTags && tags) {
            // Continue checking if there are still tags
            if (tags?.data?.length > 0) {
                // Set a timer to reload the page
                const timer = setTimeout(() => {
                    router.reload();
                }, 1000);
                return () => clearTimeout(timer);
            } else {
                setCheckingTags(false);
            }
        }
    }, [checkingTags, tags]);

    const rows = tags?.data.map((tag) => (
        <Table.Tr key={tag.id}>
            <Table.Td>
                <Checkbox
                    checked={selectedTags.includes(tag.id)}
                    onChange={() => handleSelectTag(tag.id)}
                />
            </Table.Td>
            <Table.Td>
                <Group align="center" gap="sm">
                    {tag.product?.image_url ? (
                        <img
                            src={tag.product.image_url}
                            alt={tag.product.name}
                            className="w-12 h-12 rounded object-cover"
                            style={{ minWidth: "48px" }}
                        />
                    ) : (
                        <div className="w-12 h-12 rounded bg-gray-200 flex items-center justify-center">
                            <Text size="xs" color="gray">
                                No image
                            </Text>
                        </div>
                    )}
                    <div
                        className="max-w-[200px] md:max-w-md"
                        title={tag.product?.name}
                    >
                        <Text size="sm" truncate="end">
                            {tag.product?.name || "N/A"}
                        </Text>
                    </div>
                </Group>
            </Table.Td>
            <Table.Td>
                <Text fw={500}>{tag.name}</Text>
            </Table.Td>
        </Table.Tr>
    ));

    return (
        <div className="py-12 px-2 md:px-5">
            <div className="max-w-7xl mx-auto">
                <Card withBorder shadow="sm" radius="md">
                    <Card.Section withBorder py="md" px="lg">
                        <Group justify="space-between">
                            <Text fw={600}>Generated Tags</Text>
                            <div style={{ display: "flex", gap: "6px" }}>
                                {selectedTags.length > 0 && (
                                    <Button color="red" onClick={handleDelete}>
                                        Delete Selected ({selectedTags.length})
                                    </Button>
                                )}
                                {tags?.data.length > 0 && (
                                    <Button
                                        color="red"
                                        variant="outline"
                                        onClick={handleDeleteAll}
                                    >
                                        Delete All
                                    </Button>
                                )}
                            </div>
                        </Group>
                    </Card.Section>
                    {tags?.data.length > 0 ? (
                        <Card.Section inheritPadding mt="sm" pb="md">
                            <div className="overflow-x-auto w-full">
                                <div className="min-w-[600px]">
                                    <Table striped highlightOnHover>
                                        <Table.Thead>
                                            <Table.Tr>
                                                <Table.Th>
                                                    <Checkbox
                                                        onChange={(e) =>
                                                            handleSelectAll(
                                                                e.currentTarget
                                                                    .checked
                                                            )
                                                        }
                                                        checked={
                                                            selectedTags.length ===
                                                            tags.data.length
                                                        }
                                                        indeterminate={
                                                            selectedTags.length >
                                                                0 &&
                                                            selectedTags.length <
                                                                tags.data.length
                                                        }
                                                    />
                                                </Table.Th>
                                                <Table.Th>
                                                    <p className="font-bolder uppercase">
                                                        Product
                                                    </p>
                                                </Table.Th>
                                                <Table.Th>
                                                    <p className="font-bolder uppercase">
                                                        Tag Name
                                                    </p>
                                                </Table.Th>
                                            </Table.Tr>
                                        </Table.Thead>
                                        <Table.Tbody>{rows}</Table.Tbody>
                                    </Table>
                                </div>
                            </div>
                        </Card.Section>
                    ) : (
                        <p className="pt-4 text-center italic">
                            No tags available
                        </p>
                    )}
                    {tags?.data.length > 0 && (
                        <div className="flex justify-center">
                            <Pagination.Root
                                total={tags.last_page}
                                value={tags.current_page}
                                getItemProps={(page) => ({
                                    component: "a",
                                    href: `/generated-tags?page=${page}`,
                                })}
                            >
                                <Group gap={7} mt="xl">
                                    <Pagination.First />
                                    <Pagination.Previous />
                                    <Pagination.Items />
                                    <Pagination.Next />
                                    <Pagination.Last />
                                </Group>
                            </Pagination.Root>
                        </div>
                    )}
                </Card>
            </div>
        </div>
    );
};

export default Show;
