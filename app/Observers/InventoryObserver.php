<?php

namespace App\Observers;

use App\Models\Inventory;

class InventoryObserver
{
    /**
     * Handle the Inventory "created" event.
     * 💡 Whenever a row is added to inventories table, update variant stock automatically!
     */
    public function created(Inventory $inventory): void
    {
        $variant = $inventory->variant;

        if ($variant) {
            // Increment or decrement the variant's stock based on quantity_change value (+/-)
            $variant->increment('stock', $inventory->quantity_change);
        }
    }

    /**
     * Handle the Inventory "updated" event.
     */
    public function updated(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "deleted" event.
     */
    public function deleted(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "restored" event.
     */
    public function restored(Inventory $inventory): void
    {
        //
    }

    /**
     * Handle the Inventory "force deleted" event.
     */
    public function forceDeleted(Inventory $inventory): void
    {
        //
    }
}
