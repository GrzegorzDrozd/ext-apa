<?php
for ($i = 0; $i < 200; $i++) {
    eval("class BenchClass{$i} {
        public function m1(): int { return 1; }
        public function m2(): int { return 2; }
        public function m3(): int { return 3; }
        public function m4(): int { return 4; }
        public function m5(): int { return 5; }
    }");
}
function bench_add(int $a, int $b): int { return $a + $b; }
$start = hrtime(true);
$sum = 0;
for ($i = 0; $i < 1_000_000; $i++) { $sum += bench_add($i, 1); }
$elapsed = (hrtime(true) - $start) / 1_000_000;
printf("1M calls: %.2f ms\n", $elapsed);
