import { usePage } from "@inertiajs/react";
import React, { useEffect } from "react";
import "@mantine/core/styles.css";
import {
    Anchor,
    Badge,
    Card,
    Group,
    Pagination,
    Table,
    Text,
} from "@mantine/core";
import { router } from "@inertiajs/react";

let checker = false;
export default function Show() {
    const [activity, setActivity] = React.useState([]);
    const {
        props: { comments },
    } = usePage();

    useEffect(() => {
        if (comments?.data.length > 0) {
            const activity_array = [];
            comments.data.map((comment) => {
                activity_array.push({
                    activity_id: comment.id,
                    user: comment.commenter,
                    comment: comment.message,
                    post_link: comment.post_link,
                    type: comment.post_type,
                    status: comment.private_message ? true : false,
                    date: comment.facebook_created_at,
                });
            });
            setActivity(activity_array);
        }
    }, [comments]);

    useEffect(() => {
        if (!checker) {
            setInterval(() => {
                router.reload({ only: ["comments"] });
            }, 5000);
        }
    }, []);

    const rows = activity.map((element) => (
        <Table.Tr key={element.activity_id}>
            <Table.Td>
                <div className="max-w-[120px]" title={element.user}>
                    <Text truncate="end" fw={500}>
                        {element.user}
                    </Text>
                </div>
            </Table.Td>
            <Table.Td>
                <div
                    className="max-w-[200px] md:max-w-md"
                    title={element.comment}
                >
                    <Text truncate="end">{element.comment}</Text>
                </div>
            </Table.Td>
            <Table.Td>
                <Anchor href={element.post_link} target="_blank">
                    Related post
                </Anchor>
            </Table.Td>
            <Table.Td>{element.type}</Table.Td>
            <Table.Td>
                {element.status ? (
                    <Badge color="green">Message Sent</Badge>
                ) : (
                    <Badge color="gray">No Tag Found</Badge>
                )}
            </Table.Td>
            <Table.Td>{new Date(element.date).toLocaleString()}</Table.Td>
        </Table.Tr>
    ));

    return (
        <div>
            <div className="py-12 px-2 md:px-5">
                <div className="max-w-7xl mx-auto">
                    <Card withBorder shadow="sm" radius="md">
                        <Card.Section withBorder py="md" px="lg">
                            <Group justify="space-between">
                                <Text fw={600}>Activity Log</Text>
                                <Badge
                                    className="animate-pulse"
                                    color={"#15cf4a"}
                                >
                                    <span>Live</span>
                                </Badge>
                            </Group>
                        </Card.Section>
                        {activity.length > 0 ? (
                            <Card.Section
                                inheritPadding
                                mt="sm"
                                pb="md"
                                className="overflow-x-auto"
                            >
                                <Table striped highlightOnHover>
                                    <Table.Thead>
                                        <Table.Tr>
                                            <Table.Th>
                                                <p className="font-bolder uppercase">
                                                    User
                                                </p>
                                            </Table.Th>
                                            <Table.Th>
                                                <p className="font-bolder uppercase">
                                                    Comment
                                                </p>
                                            </Table.Th>
                                            <Table.Th>
                                                <p className="font-bolder uppercase">
                                                    Post
                                                </p>
                                            </Table.Th>
                                            <Table.Th>
                                                <p className="font-bolder uppercase">
                                                    Type
                                                </p>
                                            </Table.Th>
                                            <Table.Th>
                                                <p className="font-bolder uppercase">
                                                    Status
                                                </p>
                                            </Table.Th>
                                            <Table.Th>
                                                <p className="font-bolder uppercase">
                                                    Date
                                                </p>
                                            </Table.Th>
                                        </Table.Tr>
                                    </Table.Thead>
                                    <Table.Tbody>{rows}</Table.Tbody>
                                </Table>
                            </Card.Section>
                        ) : (
                            <p className="pt-4 text-center italic">
                                No activity detected yet
                            </p>
                        )}
                        {comments?.data?.length > 0 && (
                            <div className="flex justify-center">
                                <Pagination.Root
                                    total={comments.last_page}
                                    value={comments.current_page}
                                    getItemProps={(page) => ({
                                        component: "a",
                                        href: `/dashboard?page=${page}`,
                                    })}
                                >
                                    <Group gap={7} mt="xl">
                                        <Pagination.First
                                            component="a"
                                            href={comments.first_page_url}
                                        />
                                        <Pagination.Previous
                                            component="a"
                                            href={comments.prev_page_url}
                                        />
                                        <Pagination.Items />
                                        <Pagination.Next
                                            component="a"
                                            href={comments.next_page_url}
                                        />
                                        <Pagination.Last
                                            component="a"
                                            href={comments.last_page_url}
                                        />
                                    </Group>
                                </Pagination.Root>
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </div>
    );
}
