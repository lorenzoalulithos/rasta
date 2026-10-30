<?php declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\ConsumptionMode;
use App\Enums\JobMaterialRole;
use App\Models\Database\v1\Material;

/**
 * A material of a line before calculation: its consumption rule and where coverage and
 * cost come from (the catalog, or the snapshot of an existing line, domain.md §5.7).
 */
final class MaterialSpec
{
    /**
     * @param Material        $material
     * @param JobMaterialRole $role
     * @param ConsumptionMode $consumptionMode
     * @param string|null     $consumptionPerUnit
     * @param bool            $applyCoats
     * @param bool            $selected
     * @param string|null     $snapshotCoverage   coverage of the snapshot, used instead of the catalog when $fromSnapshot
     * @param string|null     $snapshotUnitCost   unit cost of the snapshot, used instead of the catalog when $fromSnapshot
     * @param bool            $fromSnapshot       true: keep coverage and cost of the saved line
     */
    public function __construct(
        public Material $material,
        public JobMaterialRole $role,
        public ConsumptionMode $consumptionMode,
        public ?string $consumptionPerUnit,
        public bool $applyCoats,
        public bool $selected,
        public ?string $snapshotCoverage = null,
        public ?string $snapshotUnitCost = null,
        public bool $fromSnapshot = false,
    ) {
    }
}
