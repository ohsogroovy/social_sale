import { Link, router, usePage } from "@inertiajs/react";
import React, { useState } from "react";
import "@mantine/core/styles.css";
import {
    Anchor,
    Badge,
    Card,
    Group,
    Table,
    Text,
    Collapse,
    Button,
    Drawer,
    Pagination,
} from "@mantine/core";

const Show = () => {
    const { props } = usePage();
    const pastStreams = props["past-streams"];
    const [opened, setOpened] = useState(null);
    const [drawerOpened, setDrawerOpened] = useState(false);
    const [selectedComments, setSelectedComments] = useState([]);

    const toggleCollapse = (id) => {
        setOpened(opened === id ? null : id);
    };

    const openDrawer = (comments) => {
        setSelectedComments(comments);
        setDrawerOpened(true);
    };

    const handleRowClick = (postId) => {
        router.get(
            `/show-post/comment/${postId}`,
            {},
            {
                onSuccess: (page) => {
                    const { post } = page.props;
                },
            }
        );
    };

    const rows = pastStreams?.data.map((stream) => (
        <React.Fragment key={stream.id}>
            <Table.Tr onClick={() => handleRowClick(stream.id)}>
                <Table.Td
                    style={{ cursor: "pointer" }}
                    className="whitespace-nowrap"
                >
                    <Text fw={500}>{stream.id}</Text>
                </Table.Td>
                <Table.Td
                    style={{ cursor: "pointer" }}
                    className="whitespace-nowrap"
                >
                    {new Date(stream.created_at).toLocaleString()}
                </Table.Td>
                <Table.Td
                    style={{ cursor: "pointer" }}
                    className="whitespace-nowrap"
                >
                    {stream.post_type}
                </Table.Td>
                <Table.Td style={{ cursor: "pointer" }}>
                    <div className="whitespace-nowrap max-w-[250px] overflow-hidden text-ellipsis">
                        {stream.message ? stream.message : "N/A"}
                    </div>
                </Table.Td>
            </Table.Tr>
        </React.Fragment>
    ));

    return (
        <div className="py-12 px-2 md:px-5">
            <div className="max-w-7xl mx-auto">
                <Card withBorder shadow="sm" radius="md">
                    <Card.Section withBorder py="md" px="lg">
                        <Group justify="space-between">
                            <Text fw={600}>Past Live Streams</Text>
                        </Group>
                    </Card.Section>
                    <Card.Section
                        inheritPadding
                        mt="sm"
                        pb="md"
                        className="overflow-x-auto"
                    >
                        {pastStreams?.data.length > 0 ? (
                            <Table striped highlightOnHover>
                                <Table.Thead>
                                    <Table.Tr>
                                        <Table.Th>
                                            <p className="font-bolder uppercase whitespace-nowrap">
                                                ID
                                            </p>
                                        </Table.Th>
                                        <Table.Th>
                                            <p className="font-bolder uppercase whitespace-nowrap">
                                                Date
                                            </p>
                                        </Table.Th>
                                        <Table.Th>
                                            <p className="font-bolder uppercase whitespace-nowrap">
                                                Post Type
                                            </p>
                                        </Table.Th>

                                        <Table.Th>
                                            <p className="font-bolder uppercase whitespace-nowrap">
                                                Message
                                            </p>
                                        </Table.Th>
                                    </Table.Tr>
                                </Table.Thead>
                                <Table.Tbody>{rows}</Table.Tbody>
                            </Table>
                        ) : (
                            <Text
                                size="lg"
                                align="center"
                                mt="xl"
                                color="dimmed"
                            >
                                No past streams are currently available.
                            </Text>
                        )}
                    </Card.Section>
                    {pastStreams?.data.length > 0 && (
                        <div className="flex justify-center w-full">
                            <Pagination.Root
                                total={pastStreams?.last_page}
                                value={pastStreams?.current_page}
                                getItemProps={(page) => ({
                                    component: "a",
                                    href: `/past-streams?page=${page}`,
                                })}
                            >
                                <Group gap={7} mt="xl">
                                    <Pagination.First
                                        component="a"
                                        href={pastStreams?.first_page_url}
                                    />
                                    <Pagination.Previous
                                        component="a"
                                        href={pastStreams?.prev_page_url}
                                    />
                                    <Pagination.Items />
                                    <Pagination.Next
                                        component="a"
                                        href={pastStreams?.next_page_url}
                                    />
                                    <Pagination.Last
                                        component="a"
                                        href={pastStreams?.last_page_url}
                                    />
                                </Group>
                            </Pagination.Root>
                        </div>
                    )}
                </Card>
            </div>

            {/* Drawer for comments */}
            <Drawer
                opened={drawerOpened}
                onClose={() => setDrawerOpened(false)}
                position="right"
                title="Comments"
                padding="md"
                size="lg"
            >
                {selectedComments.length > 0 ? (
                    <div>
                        <div className="grid grid-cols-3 gap-4 font-bold">
                            <div>User</div>
                            <div>Comment</div>
                            <div>Date</div>
                        </div>
                        {selectedComments.map((comment) => (
                            <div
                                key={comment.id}
                                className="grid grid-cols-3 gap-4 border-t pt-2"
                            >
                                <Text>{comment.commenter}</Text>
                                <Text>{comment.message}</Text>
                                <div>
                                    <Anchor
                                        href={comment.post_link}
                                        target="_blank"
                                    >
                                        View Post
                                    </Anchor>
                                    <br />
                                    <Text size="sm" color="dimmed">
                                        {new Date(
                                            comment.created_at
                                        ).toLocaleString()}
                                    </Text>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <Text size="sm" color="dimmed">
                        No comments available
                    </Text>
                )}
            </Drawer>
        </div>
    );
};

export default Show;
