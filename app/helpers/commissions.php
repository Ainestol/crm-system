<?php
// e:\Snecinatripu\app\helpers\commissions.php
declare(strict_types=1);

/**
 * Výběr násobků z DB a základní aritmetika provizí (plný engine až ve kroku 27).
 */

if (!function_exists('commissions_pick_sales_multiplier')) {
    /**
     * Násobek ceny obchodu pro obchodáka podle měsíčního obratu (commission_tiers_sales).
     */
    function commissions_pick_sales_multiplier(PDO $pdo, float $monthlySalesTotal): float
    {
        $stmt = $pdo->query(
            'SELECT min_monthly_sales, max_monthly_sales, multiplier
             FROM commission_tiers_sales
             ORDER BY min_monthly_sales ASC'
        );
        $rows = $stmt->fetchAll();
        if (!is_array($rows) || $rows === []) {
            return 5.0;
        }
        foreach ($rows as $r) {
            $min = (float) $r['min_monthly_sales'];
            $maxRaw = $r['max_monthly_sales'];
            $max = $maxRaw === null ? null : (float) $maxRaw;
            if ($monthlySalesTotal + 0.00001 < $min) {
                continue;
            }
            if ($max !== null && $monthlySalesTotal > $max + 0.00001) {
                continue;
            }
            return (float) $r['multiplier'];
        }
        $last = $rows[count($rows) - 1];
        return (float) $last['multiplier'];
    }
}

if (!function_exists('commissions_pick_company_multiplier')) {
    /**
     * Násobek od velké firmy podle typu služby a ceny bez DPH (commission_tiers_company).
     */
    function commissions_pick_company_multiplier(
        PDO $pdo,
        string $serviceType,
        float $priceNoVat
    ): ?float {
        $stmt = $pdo->prepare(
            'SELECT multiplier FROM commission_tiers_company
             WHERE service_type = :st
               AND min_price <= :p
               AND (max_price IS NULL OR max_price >= :p2)
             ORDER BY min_price DESC
             LIMIT 1'
        );
        $stmt->execute(['st' => $serviceType, 'p' => $priceNoVat, 'p2' => $priceNoVat]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        return (float) $row['multiplier'];
    }
}

if (!function_exists('commissions_caller_reward_czk')) {
    /**
     * Aktuální fixní odměna navolávačky za CALLED_OK (caller_rewards_config).
     */
    function commissions_caller_reward_czk(PDO $pdo, ?DateTimeInterface $on = null): ?float
    {
        $on ??= new DateTimeImmutable('now');
        $d = $on->format('Y-m-d');
        $stmt = $pdo->prepare(
            'SELECT amount_czk FROM caller_rewards_config
             WHERE valid_from <= :d AND (valid_to IS NULL OR valid_to >= :d2)
             ORDER BY valid_from DESC
             LIMIT 1'
        );
        $stmt->execute(['d' => $d, 'd2' => $d]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        return (float) $row['amount_czk'];
    }
}

if (!function_exists('commissions_sales_payment_czk')) {
    /** Provize obchodáka = cena obchodu × násobek výkonu. */
    function commissions_sales_payment_czk(float $salePriceNoVat, float $salesMultiplier): float
    {
        return round($salePriceNoVat * $salesMultiplier, 2);
    }
}

if (!function_exists('commissions_company_income_czk')) {
    /** Částka od velké firmy = cena obchodu × násobek company tier. */
    function commissions_company_income_czk(float $salePriceNoVat, float $companyMultiplier): float
    {
        return round($salePriceNoVat * $companyMultiplier, 2);
    }
}

if (!function_exists('commissions_margin_after_sales_czk')) {
    /**
     * Marže firmy po výplatě obchodákovi (bez odměny navolávačky) – orientační výpočet.
     */
    function commissions_margin_after_sales_czk(float $companyIncome, float $salesPayment): float
    {
        return round(max(0.0, $companyIncome - $salesPayment), 2);
    }
}

if (!function_exists('commissions_cancellation_deduction_sales_czk')) {
    /**
     * Poměrná srážka z provize obchodáka při stornu (cancellation_ratio 0–1).
     */
    function commissions_cancellation_deduction_sales_czk(
        float $originalSalesCommission,
        float $cancellationRatio
    ): float {
        $ratio = max(0.0, min(1.0, $cancellationRatio));
        return round($originalSalesCommission * $ratio, 2);
    }
}
