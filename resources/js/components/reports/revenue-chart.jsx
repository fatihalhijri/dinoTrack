import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import Money from '@/components/money';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
} from '@/components/ui/chart';
import { formatMonth, formatRupiahCompact } from '@/lib/format';
/** @import { ChartConfig } from '@/components/ui/chart' */
/** @import { MonthlyRevenue, PaymentMethod, SelectOption } from '@/types' */

/** Warna mengikuti metode (bukan urutan tampil) agar tetap sama di semua grafik. */
/** @type {Record<PaymentMethod, string>} */
const METHOD_COLORS = {
    qris: 'var(--chart-1)',
    cash: 'var(--chart-2)',
    transfer: 'var(--chart-3)',
};

/** @typedef {Record<PaymentMethod, number> & { month: number }} ChartRow */

/**
 * @param {{
 *     row: MonthlyRevenue;
 *     year: number;
 *     methods: SelectOption<PaymentMethod>[];
 * }} props
 */
function RevenueTooltip({ row, year, methods }) {
    return (
        <div className="grid min-w-44 gap-1.5 rounded-lg border bg-background px-3 py-2 text-xs shadow-xl">
            <p className="font-medium">
                {formatMonth(row.month)} {year}
            </p>
            {methods.map((method) => (
                <div
                    key={method.value}
                    className="flex items-center justify-between gap-4"
                >
                    <span className="flex items-center gap-1.5 text-muted-foreground">
                        <span
                            className="size-2.5 shrink-0 rounded-[2px]"
                            style={{
                                backgroundColor: METHOD_COLORS[method.value],
                            }}
                            aria-hidden="true"
                        />
                        {method.label}
                    </span>
                    <Money amount={row.by_method[method.value]} />
                </div>
            ))}
            <div className="flex items-center justify-between gap-4 border-t pt-1.5 font-medium">
                <span>Total</span>
                <Money amount={row.total} />
            </div>
        </div>
    );
}

/**
 * Pendapatan 12 bulan bertumpuk per metode. Angka lengkap selalu tersedia di tabel di
 * bawahnya; grafik hanya ringkasan visual.
 *
 * @param {{
 *     revenue: MonthlyRevenue[];
 *     year: number;
 *     methods: SelectOption<PaymentMethod>[];
 * }} props
 */
export default function RevenueChart({ revenue, year, methods }) {
    /** @type {ChartConfig} */
    const config = Object.fromEntries(
        methods.map((method) => [
            method.value,
            { label: method.label, color: METHOD_COLORS[method.value] },
        ]),
    );
    /** @type {ChartRow[]} */
    const rows = revenue.map((month) => ({
        month: month.month,
        ...month.by_method,
    }));
    const topMethod = methods.at(-1)?.value;

    return (
        <ChartContainer
            config={config}
            className="aspect-auto h-64 w-full sm:h-72"
            role="img"
            aria-label={`Grafik pendapatan per bulan tahun ${year}, bertumpuk per metode pembayaran. Angka lengkap ada di tabel di bawahnya.`}
        >
            <BarChart
                data={rows}
                accessibilityLayer
                margin={{ top: 8, right: 4, left: 4, bottom: 0 }}
            >
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="month"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    interval="preserveStartEnd"
                    minTickGap={4}
                    tickFormatter={(/** @type {number} */ month) =>
                        formatMonth(month, { short: true })
                    }
                />
                <YAxis
                    tickLine={false}
                    axisLine={false}
                    width={64}
                    allowDecimals={false}
                    tickFormatter={(/** @type {number} */ amount) =>
                        formatRupiahCompact(amount)
                    }
                />
                <ChartTooltip
                    cursor={{ fill: 'var(--muted)' }}
                    content={({ active, label }) => {
                        const row = revenue.find(
                            (month) => month.month === label,
                        );

                        return active && row ? (
                            <RevenueTooltip
                                row={row}
                                year={year}
                                methods={methods}
                            />
                        ) : null;
                    }}
                />
                {/* Urutan legenda = urutan tumpukan, bukan abjad (bawaan Recharts). */}
                <ChartLegend
                    verticalAlign="top"
                    itemSorter={null}
                    content={<ChartLegendContent verticalAlign="top" />}
                />
                {methods.map((method) => (
                    <Bar
                        key={method.value}
                        dataKey={method.value}
                        name={method.label}
                        stackId="revenue"
                        fill={`var(--color-${method.value})`}
                        stroke="var(--card)"
                        strokeWidth={2}
                        maxBarSize={36}
                        radius={method.value === topMethod ? [4, 4, 0, 0] : 0}
                    />
                ))}
            </BarChart>
        </ChartContainer>
    );
}
