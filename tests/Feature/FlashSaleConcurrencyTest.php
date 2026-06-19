<?php

namespace Tests\Feature;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Tests\TestCase;

class FlashSaleConcurrencyTest extends TestCase
{
    private string $databasePath;

    private string $workerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = database_path('testing-flash-sale.sqlite');
        $this->workerPath = storage_path('framework/testing-flash-sale-worker.php');

        $this->deleteFileIfExists($this->databasePath);
        $this->deleteFileIfExists($this->workerPath);

        touch($this->databasePath);
        $this->useFileBackedSqliteDatabase($this->databasePath);
        $this->writeWorkerScript($this->workerPath);

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath)) {
            $this->deleteFileIfExists($this->databasePath);
        }

        if (isset($this->workerPath)) {
            $this->deleteFileIfExists($this->workerPath);
        }

        parent::tearDown();
    }

    public function test_flash_sale_orders_never_oversell_inventory_under_concurrency(): void
    {
        $initialInventory = 5;
        $attempts = 20;

        $product = Product::query()->create([
            'name' => 'Doorbuster Headphones',
            'inventory_quantity' => $initialInventory,
            'price' => 250_000,
            'discount_price' => 25_000,
        ]);

        $processes = Process::concurrently(function (Pool $pool) use ($attempts, $product): void {
            foreach (range(1, $attempts) as $attempt) {
                $pool->as((string) $attempt)
                    ->path(base_path())
                    ->timeout(30)
                    ->command($this->workerCommand($product->id));
            }
        });

        $this->assertTrue(
            $processes->successful(),
            $processes->collect()
                ->reject->successful()
                ->map(fn ($process) => $process->errorOutput() ?: $process->output())
                ->implode(PHP_EOL),
        );

        $results = $processes->collect()
            ->map(fn ($process) => json_decode($process->output(), true, flags: JSON_THROW_ON_ERROR));

        $successfulOrders = $results->where('status', 'created')->count();
        $rejectedOrders = $results->where('status', 'conflict')->count();
        $unexpectedErrors = $results->where('status', 'error')->values();

        $product->refresh();

        $this->assertSame([], $unexpectedErrors->all());
        $this->assertSame($initialInventory, $successfulOrders);
        $this->assertSame($attempts - $initialInventory, $rejectedOrders);
        $this->assertSame(0, $product->inventory_quantity);
        $this->assertSame($initialInventory, Order::query()->count());
        $this->assertSame($initialInventory, OrderItem::query()->sum('quantity'));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'inventory_quantity' => 0,
        ]);
    }

    private function workerCommand(int $productId): string
    {
        return sprintf(
            '"%s" "%s" "%s" %d',
            PHP_BINARY,
            $this->workerPath,
            $this->databasePath,
            $productId,
        );
    }

    private function useFileBackedSqliteDatabase(string $databasePath): void
    {
        config(['database.connections.sqlite.database' => $databasePath]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    private function writeWorkerScript(string $workerPath): void
    {
        file_put_contents($workerPath, <<<'PHP'
<?php

use App\Exceptions\InsufficientInventoryException;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Throwable;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$databasePath = $argv[1];
$productId = (int) $argv[2];

config(['database.connections.sqlite.database' => $databasePath]);
DB::purge('sqlite');
DB::reconnect('sqlite');

try {
    app(OrderService::class)->create([
        [
            'product_id' => $productId,
            'quantity' => 1,
        ],
    ]);

    echo json_encode(['status' => 'created'], JSON_THROW_ON_ERROR);
} catch (InsufficientInventoryException) {
    echo json_encode(['status' => 'conflict'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode([
        'status' => 'error',
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
PHP);
    }

    private function deleteFileIfExists(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
