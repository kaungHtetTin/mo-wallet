<?php

return [
    'up' => [
        'ALTER TABLE books MODIFY opening_balance DECIMAL(21,2) NOT NULL DEFAULT 0.00',
        'ALTER TABLE debts MODIFY amount DECIMAL(21,2) NOT NULL',
        'ALTER TABLE transactions MODIFY amount DECIMAL(21,2) NOT NULL',
    ],
    // Retain the compatible decimal columns on rollback to avoid losing fractions.
    'down' => [],
];
