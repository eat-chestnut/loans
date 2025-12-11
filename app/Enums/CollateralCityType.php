<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static FUZHOU()
 * @method static static MINHOU()
 */
final class CollateralCityType extends Enum
{

    #[Description('福州市')]
    const FUZHOU = 1;

    #[Description('闽侯县')]
    const MINHOU = 2;
}
