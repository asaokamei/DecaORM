<?php

namespace WScore\DecaORM\Sql;

use InvalidArgumentException;

trait IdentifierQuoteTrait
{
    /** @var string|null null = quoting disabled (unknown driver or not configured) */
    private ?string $identifierQuote = null;
    private ?string $driverName = null;

    /**
     * Enable identifier quoting for supported PDO drivers only (mysql, pgsql, sqlite).
     * Unknown or null driver disables quoting.
     */
    public function setIdentifierQuoteByDriver(?string $driverName): static
    {
        $normalized = strtolower((string) $driverName);
        $this->driverName = $normalized !== '' ? $normalized : null;
        $this->identifierQuote = match ($normalized) {
            'mysql' => '`',
            'pgsql', 'sqlite' => '"',
            default => null,
        };
        return $this;
    }

    protected function isSqliteDriver(): bool
    {
        return $this->driverName === 'sqlite';
    }

    protected function isIdentifierQuotingEnabled(): bool
    {
        return $this->identifierQuote !== null && $this->identifierQuote !== '';
    }

    protected function getIdentifierQuoteChar(): string
    {
        return $this->identifierQuote ?? '';
    }

    protected function quoteIdentifierPart(string $identifierPart): string
    {
        if (!$this->isIdentifierQuotingEnabled()) {
            return $identifierPart;
        }
        $quote = $this->getIdentifierQuoteChar();
        $escaped = str_replace($quote, $quote . $quote, $identifierPart);
        return $quote . $escaped . $quote;
    }

    protected function escapeColumnIdentifier(string $identifier): string
    {
        if (preg_match('/^(.*)\s+AS\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $identifier, $matches) === 1) {
            $column = trim($matches[1]);
            $alias = $matches[2];
            return $this->escapeColumnIdentifier($column) . ' AS ' . $this->quoteIdentifierPart($alias);
        }
        if (preg_match('/^(.*)\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $identifier, $matches) === 1) {
            $column = trim($matches[1]);
            $alias = $matches[2];
            // Check if column is not empty to avoid matching a single word with leading space
            if ($column !== '') {
                return $this->escapeColumnIdentifier($column) . ' AS ' . $this->quoteIdentifierPart($alias);
            }
        }

        if ($identifier === '*' || str_ends_with($identifier, '.*')) {
            if ($identifier === '*') {
                return $identifier;
            }
            $prefix = substr($identifier, 0, -2);
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $prefix) === 1) {
                return $this->quoteIdentifierPart($prefix) . '.*';
            }
            throw new InvalidArgumentException(
                "Invalid column identifier: {$identifier}. Use a raw method for expressions."
            );
        }

        $parts = explode('.', $identifier);
        if ($parts === [] || count($parts) > 2) {
            throw new InvalidArgumentException(
                "Invalid column identifier: {$identifier}. Use a raw method for expressions."
            );
        }
        foreach ($parts as $part) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid column identifier: {$identifier}. Use a raw method for expressions."
                );
            }
        }
        $escaped = array_map(fn(string $part): string => $this->quoteIdentifierPart($part), $parts);
        return implode('.', $escaped);
    }

    protected function escapeTableReference(string $table): string
    {
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*))?$/i', $table, $matches) !== 1) {
            throw new InvalidArgumentException(
                "Invalid table reference: {$table}. Use fromRaw() for raw FROM fragments."
            );
        }
        if (!isset($matches[2]) || $matches[2] === '') {
            return $this->quoteIdentifierPart($matches[1]);
        }
        return $this->quoteIdentifierPart($matches[1]) . ' ' . $this->quoteIdentifierPart($matches[2]);
    }
}
