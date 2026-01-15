<?php

namespace Tests\Feature\Actions;

use Mockery;
use Tests\TestCase;
use App\Models\Comment;
use App\Clients\Facebook;
use App\Clients\SmartCart;
use App\Actions\PromptAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PromptAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private PromptAuthorization $action;

    private $facebookMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facebookMock = Mockery::mock(Facebook::class);
        $this->action = new PromptAuthorization($this->facebookMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_authorization_message_to_commenter(): void
    {
        // Arrange
        $comment = Comment::factory()->create([
            'commenter' => 'John Doe',
        ]);

        $userData = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $expectedMessage = [
            'recipient' => ['comment_id' => $comment->id],
            'message' => [
                'attachment' => [
                    'type' => 'template',
                    'payload' => [
                        'template_type' => 'generic',
                        'elements' => [
                            [
                                'title' => 'Authorize with Hotsy Totsy',
                                'subtitle' => 'Click the button below to authorize with us',
                                'buttons' => [
                                    [
                                        'type' => 'web_url',
                                        'url' => SmartCart::authorizationUrl(['name' => $comment->commenter]),
                                        'title' => 'Authorize',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($expectedMessage) {
                return $message['recipient'] === $expectedMessage['recipient'] &&
                       $message['message']['attachment']['type'] === $expectedMessage['message']['attachment']['type'] &&
                       $message['message']['attachment']['payload']['template_type'] === $expectedMessage['message']['attachment']['payload']['template_type'];
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($userData, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_sends_authorization_message_to_user(): void
    {
        // Arrange
        $comment = Comment::factory()->create([
            'commenter' => 'John Doe',
        ]);

        $userData = [
            'type' => 'user',
            'id' => 'test_user_id',
        ];

        $expectedMessage = [
            'recipient' => ['id' => 'test_user_id'],
            'message' => [
                'attachment' => [
                    'type' => 'template',
                    'payload' => [
                        'template_type' => 'generic',
                        'elements' => [
                            [
                                'title' => 'Authorize with Hotsy Totsy',
                                'subtitle' => 'Click the button below to authorize with us',
                                'buttons' => [
                                    [
                                        'type' => 'web_url',
                                        'url' => SmartCart::authorizationUrl(['name' => $comment->commenter]),
                                        'title' => 'Authorize',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($expectedMessage) {
                return $message['recipient'] === $expectedMessage['recipient'] &&
                       $message['message']['attachment']['type'] === $expectedMessage['message']['attachment']['type'] &&
                       $message['message']['attachment']['payload']['template_type'] === $expectedMessage['message']['attachment']['payload']['template_type'];
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($userData, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_logs_message_sending_activity(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $userData = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($userData, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }
}
