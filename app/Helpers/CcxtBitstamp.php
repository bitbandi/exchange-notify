<?php

namespace App\Helpers;

use \ccxt\bitstamp as BaseBitstamp;

class CcxtBitstamp extends BaseBitstamp
{
    // no func in ccxt, emulate this
    public function describe() {
        return $this->deep_extend(parent::describe(), array(
            'has' => array(
                'fetchDeposits' => true,
            ),
        ));
    }

    public function parse_cryptotransactions($type, array $transactions, ?array $currency = null, ?int $since = null, ?int $limit = null, $params = array ()) {
        $transactions = $this->to_array($transactions);
        $result = array();
        for ($i = 0; $i < count($transactions); $i++) {
            $transaction = $this->extend($this->parse_cryptotransaction($type, $transactions[$i], $currency), $params);
            $result[] = $transaction;
        }
        $result = $this->sort_by($result, 'timestamp');
        $code = ($currency !== null) ? $currency['code'] : null;
        return $this->filter_by_currency_since_limit($result, $code, $since, $limit);
    }

    public function parse_cryptotransaction($type, array $transaction, ?array $currency = null): array {
        $timestamp = $this->safe_integer_product($transaction, 'datetime', 1000);
        $currencyId = $this->get_currency_id_from_transaction($transaction);
        $code = $this->safe_currency_code($currencyId, $currency);
        $amount = $this->safe_string($transaction, 'amount');
        $address = $this->safe_string($transaction, 'destinationAddress');
        return array(
            'info' => $transaction,
            'id' => $this->safe_string($transaction, 'id'),
            'txid' => $this->safe_string($transaction, 'txid'),
            'type' => $type,
            'currency' => $code,
            'network' => $this->safe_string($transaction, 'network'),
            'amount' => $this->parse_number($amount),
            'status' => "ok",
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'address' => $address,
            'addressFrom' => null,
            'addressTo' => $address,
            'tag' => null,
            'tagFrom' => null,
            'tagTo' => null,
            'updated' => null,
            'comment' => null,
            'internal' => null,
            'fee' => null,
        );
    }

    public function fetch_deposits(?string $code = null, ?int $since = null, ?int $limit = null, $params = array ()): array {
        $this->load_markets();
        $request = array();
        if ($limit !== null) {
            $request['limit'] = $limit;
        }
        $response = $this->privatePostCryptoTransactions ($this->extend($request, $params));
        $currency = null;
        if ($code !== null) {
            $currency = $this->currency($code);
        }
        return $this->parse_cryptotransactions("deposit", $response["deposits"], $currency, $since, $limit);
    }

    public function fetch_withdrawals(?string $code = null, ?int $since = null, ?int $limit = null, $params = array ()): array {
        $this->load_markets();
        $request = array();
        if ($limit !== null) {
            $request['limit'] = $limit;
        }
        $response = $this->privatePostCryptoTransactions ($this->extend($request, $params));
        $currency = null;
        if ($code !== null) {
            $currency = $this->currency($code);
        }
        return $this->parse_cryptotransactions("withdrawal", $response["withdrawals"], $currency, $since, $limit);
    }
}
