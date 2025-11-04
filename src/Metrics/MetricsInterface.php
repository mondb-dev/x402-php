<?php

declare(strict_types=1);

namespace X402\Metrics;

/**
 * Interface for recording metrics and monitoring.
 */
interface MetricsInterface
{
    /**
     * Increment a counter metric.
     *
     * @param string $name Metric name
     * @param array<string, string>|int $valueOrTags Value to increment or tags array
     * @param array<string, string> $tags Optional tags for the metric (if first param is int)
     * @return void
     */
    public function incrementCounter(string $name, array|int $valueOrTags = 1, array $tags = []): void;

    /**
     * Record a timing metric (in milliseconds or seconds).
     *
     * @param string $name Metric name
     * @param float $duration Duration value
     * @param array<string, string> $tags Optional tags for the metric
     * @return void
     */
    public function recordTiming(string $name, float $duration, array $tags = []): void;

    /**
     * Record a gauge metric (current value).
     *
     * @param string $name Metric name
     * @param float $value Current value
     * @param array<string, string> $tags Optional tags for the metric
     * @return void
     */
    public function recordGauge(string $name, float $value, array $tags = []): void;

    /**
     * Record a histogram metric (distribution of values).
     *
     * @param string $name Metric name
     * @param float $value Value to record
     * @param array<string, string> $tags Optional tags for the metric
     * @return void
     */
    public function recordHistogram(string $name, float $value, array $tags = []): void;
}
