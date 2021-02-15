<?php 
class PIPConfig {
    public static function MAX_ALLOWED(){
        return get_option('cred_max_credit', 10000);
    }
}