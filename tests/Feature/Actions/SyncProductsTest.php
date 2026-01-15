<?php

namespace Tests\Feature\Actions;

use Mockery;
use ArrayIterator;
use Tests\TestCase;
use App\Clients\Shopify;
use App\Actions\AddProduct;
use App\Actions\SyncProducts;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncProductsTest extends TestCase
{
    use RefreshDatabase;

    private SyncProducts $action;

    private $shopifyMock;

    private $addProductMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shopifyMock = Mockery::mock(Shopify::class);
        $this->addProductMock = Mockery::mock(AddProduct::class);

        $this->action = new SyncProducts(
            $this->shopifyMock,
            $this->addProductMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_syncs_products_without_progress_bar(): void
    {
        // Arrange
        $products = [
            ['id' => 12345, 'title' => 'Product 1', 'body_html' => '<p>Description 1</p>'],
            ['id' => 67890, 'title' => 'Product 2', 'body_html' => '<p>Description 2</p>'],
        ];

        // Expectations
        $this->shopifyMock
            ->shouldReceive('getAllProductsWithSeoDescription')
            ->once()
            ->andReturn(new ArrayIterator($products));

        // Expect AddProduct to be called for each product
        $this->addProductMock
            ->shouldReceive('execute')
            ->once()
            ->with($products[0]);

        $this->addProductMock
            ->shouldReceive('execute')
            ->once()
            ->with($products[1]);

        // Act
        $this->action->execute();

        // Assert - Add explicit assertion to avoid "risky" test warning
        $this->assertTrue(true, 'Products were synced successfully without a progress bar');
    }

    public function test_syncs_products_with_progress_bar(): void
    {
        // Arrange
        $products = [
            ['id' => 12345, 'title' => 'Product 1', 'body_html' => '<p>Description 1</p>'],
            ['id' => 67890, 'title' => 'Product 2', 'body_html' => '<p>Description 2</p>'],
            ['id' => 13579, 'title' => 'Product 3', 'body_html' => '<p>Description 3</p>'],
        ];

        // Create a progress bar with a null output to prevent console output during tests
        $output = new NullOutput;
        $progressBar = new ProgressBar($output);
        $progressBar->start(count($products));

        // Expectations
        $this->shopifyMock
            ->shouldReceive('getAllProductsWithSeoDescription')
            ->once()
            ->andReturn(new ArrayIterator($products));

        // Expect AddProduct to be called for each product
        $this->addProductMock
            ->shouldReceive('execute')
            ->times(3);

        // Act
        $this->action->execute($progressBar);

        // Assert - Check that the progress bar was advanced for each product and then finished
        $this->assertEquals(3, $progressBar->getProgress(), 'Progress bar should be advanced for each product');
    }

    public function test_handles_empty_product_list(): void
    {
        // Arrange
        $products = [];

        // Expectations
        $this->shopifyMock
            ->shouldReceive('getAllProductsWithSeoDescription')
            ->once()
            ->andReturn(new ArrayIterator($products));

        // AddProduct should not be called
        $this->addProductMock
            ->shouldNotReceive('execute');

        // Act
        $this->action->execute();

        // Assert - Add explicit assertion to avoid "risky" test warning
        $this->assertTrue(true, 'Empty product list was handled gracefully');
    }

    public function test_syncs_products_with_varying_data_structures(): void
    {
        // Arrange
        $products = [
            // Standard product with all fields
            [
                'id' => 12345,
                'title' => 'Complete Product',
                'body_html' => '<p>Full description</p>',
                'images' => [['src' => 'https://example.com/image1.jpg']],
                'variants' => [['id' => 111, 'price' => '19.99']],
            ],
            // Minimal product with only required fields
            [
                'id' => 67890,
                'title' => 'Minimal Product',
            ],
            // Product with empty/null fields
            [
                'id' => 13579,
                'title' => 'Partial Product',
                'body_html' => null,
                'images' => [],
            ],
        ];

        // Expectations
        $this->shopifyMock
            ->shouldReceive('getAllProductsWithSeoDescription')
            ->once()
            ->andReturn(new ArrayIterator($products));

        // Expect AddProduct to be called for each product, regardless of structure
        $this->addProductMock
            ->shouldReceive('execute')
            ->once()
            ->with($products[0]);

        $this->addProductMock
            ->shouldReceive('execute')
            ->once()
            ->with($products[1]);

        $this->addProductMock
            ->shouldReceive('execute')
            ->once()
            ->with($products[2]);

        // Act
        $this->action->execute();

        // Assert - Add explicit assertion to avoid "risky" test warning
        $this->assertTrue(true, 'Products with varying data structures were processed correctly');
    }

    public function test_handles_large_product_list(): void
    {
        // Arrange - Create a large list of products (not too large for testing)
        $products = [];
        for ($i = 0; $i < 50; $i++) {
            $products[] = [
                'id' => 10000 + $i,
                'title' => "Product {$i}",
                'body_html' => "<p>Description for product {$i}</p>",
            ];
        }

        // Expectations
        $this->shopifyMock
            ->shouldReceive('getAllProductsWithSeoDescription')
            ->once()
            ->andReturn(new ArrayIterator($products));

        // AddProduct should be called for each product
        $this->addProductMock
            ->shouldReceive('execute')
            ->times(50);

        // Create a progress bar to track the large batch
        $output = new NullOutput;
        $progressBar = new ProgressBar($output);
        $progressBar->start(count($products));

        // Act
        $this->action->execute($progressBar);

        // Assert
        $this->assertEquals(50, $progressBar->getProgress(), 'Progress bar should advance for all products');
    }
}
