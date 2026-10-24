<?php

return [
    'up' => [
        'ALTER TABLE books MODIFY opening_balance DECIMAL(22,3) NOT NULL DEFAULT 0.000',
        'ALTER TABLE debts MODIFY amount DECIMAL(22,3) NOT NULL',
        'ALTER TABLE transactions MODIFY amount DECIMAL(22,3) NOT NULL',
    ],
    // Keep the wider columns on rollback so existing thousandths are preserved.
    'down' => [],
];
