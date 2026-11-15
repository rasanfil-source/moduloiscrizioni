<?php
ob_start(); require __DIR__ . '/email-outcomes-audit.php'; ob_end_clean();
class ErrorLengthDatabase extends OutcomeAuditDatabase {
    public $deferred = false;
    function prepare($sql,...$args) {
        if (str_contains($sql, 'attempts=GREATEST')) {
            if (mb_strlen($args[1], 'UTF-8') > 190) throw new RuntimeException('Deferred error exceeds varchar');
            $this->deferred = true;
        }
        return parent::prepare($sql,...$args);
    }
    function update($table,$data,$where,...$args) {
        if (isset($data['last_error']) && mb_strlen($data['last_error'], 'UTF-8') > 190) return false;
        return parent::update($table,$data,$where,...$args);
    }
}
foreach (['', 'EMAIL_STORAGE_UNAVAILABLE', 'EMAIL_DELIVERY_UNCERTAIN'] as $code) {
    $wpdb = new ErrorLengthDatabase();
    $row = ['id'=>1,'status'=>'TEST_PENDING','attempts'=>0,'event_id'=>42];
    $wpdb->rows[1] = array_replace($row,['status'=>'TEST_SENDING']);
    $outcome->invoke(null,$row,new WP_Error('remote',str_repeat('è🙂',300),['remote_code'=>$code]));
    if ($code === 'EMAIL_STORAGE_UNAVAILABLE') {
        if (!$wpdb->deferred) throw new RuntimeException('Deferred path missed');
    } else {
        $saved = $wpdb->rows[1];
        if ($saved['status'] === 'TEST_SENDING' || mb_strlen($saved['last_error'],'UTF-8') !== 190 || !mb_check_encoding($saved['last_error'],'UTF-8')) throw new RuntimeException('Outcome lost or UTF-8 damaged');
    }
}
echo "PASS: long Unicode errors preserve deferred, retry and terminal outcomes within 190 characters.\n";
