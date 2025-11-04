<?php

declare(strict_types=1);

namespace X402\Metrics;

/**
 * Default in-memory metrics implementation.
 * 
 * Stores metrics in memory for debugging and testing.
 * For production, use a proper metrics backend (StatsD, Prometheus, etc.).
 */
class DefaultMetrics implements MetricsInterface
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, array<float>> */
    private array $timings = [];

    /** @var array<string, float> */
    private array $gauges = [];

    /** @var array<string, array<float>> */
    private array $histograms = [];

    /**
     * @inheritDoc
     */
    public function incrementCounter(string $name, array|int $valueOrTags = 1, array $tags = []): void
    {
        // Support both signatures: incrementCounter('name', ['tag'=>'val']) and incrementCounter('name', 1, ['tag'=>'val'])
        if (is_array($valueOrTags)) {
            $tags = $valueOrTags;
            $value = 1;
        } else {
            $value = $valueOrTags;
        }

        $key = $this->buildKey($name, $tags);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;
    }

    /**
     * @inheritDoc
     */
    public function recordTiming(string $name, float $duration, array $tags = []): void
    {
        $key = $this->buildKey($name, $tags);
        $this->timings[$key][] = $duration;
    }

    /**
     * @inheritDoc
     */
    public function recordGauge(string $name, float $value, array $tags = []): void
    {
        $key = $this->buildKey($name, $tags);
        $this->gauges[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function recordHistogram(string $name, float $value, array $tags = []): void
    {
        $key = $this->buildKey($name, $tags);
        $this->histograms[$key][] = $value;
    }

    /**
     * Get all collected metrics with statistics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return [
            'counters' => $this->counters,
            'timings' => $this->calculateTimingStats(),
            'gauges' => $this->gauges,
            'histograms' => $this->calculateHistogramStats(),
        ];
    }

    /**
     * Reset all metrics.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->counters = [];
        $this->timings = [];
        $this->gauges = [];
        $this->histograms = [];
    }

    /**
     * Build a key from metric name and tags.
     *
     * @param string $name
     * @param array<string, string> $tags
     * @return string
     */
    private function buildKey(string $name, array $tags): string
    {
        if (empty($tags)) {
            return $name;
        }

        ksort($tags);
        $tagString = implode(',', array_map(
            fn($k, $v) => "$k=$v",
            array_keys($tags),
            array_values($tags)
        ));

        return "$name{$tagString}";
    }

    /**
     * Calculate statistics for timing metrics.
     *
     * @return array<string, array<string, mixed>>
     */
    private function calculateTimingStats(): array
    {
        $stats = [];
        foreach ($this->timings as $key => $values) {
            if (empty($values)) {
                continue;
            }

            sort($values);
            $count = count($values);
            
            $stats[$key] = [
                'count' => $count,
                'mean' => array_sum($values) / $count,
                'min' => $values[0],
                'max' => $values[$count - 1],
                'p50' => $this->percentile($values, 50),
                'p95' => $this->percentile($values, 95),
                'p99' => $this->percentile($values, 99),
            ];
        }
        return $stats;
    }

    /**
     * Calculate statistics for histogram metrics.
     *
     * @return array<string, array<string, mixed>>
     */
    private function calculateHistogramStats(): array
    {
        $stats = [];
        foreach ($this->histograms as $key => $values) {
            if (empty($values)) {
                continue;
            }

            sort($values);
            $count = count($values);
            
            $stats[$key] = [
                'count' => $count,
                'mean' => array_sum($values) / $count,
                'min' => $values[0],
                'max' => $values[$count - 1],
                'p50' => $this->percentile($values, 50),
                'p95' => $this->percentile($values, 95),
                'p99' => $this->percentile($values, 99),
            ];
        }
        return $stats;
    }

    /**
     * Calculate percentile from sorted array.
     *
     * @param array<float> $values Sorted array of values
     * @param int $percentile Percentile to calculate (0-100)
     * @return float
     */
    private function percentile(array $values, int $percentile): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $index = ($percentile / 100) * ($count - 1);
        $lower = (int)floor($index);
        $upper = (int)ceil($index);

        if ($lower === $upper) {
            return $values[$lower];
        }

        $fraction = $index - $lower;
        return $values[$lower] + ($values[$upper] - $values[$lower]) * $fraction;
    }
}
