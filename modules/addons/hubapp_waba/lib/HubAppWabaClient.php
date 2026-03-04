<?php
namespace HubAppWabaModule;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) die("Access Denied");

/**
 * HubApp WABA Client - Conexão API Oficial Meta
 * Versão 1.1.0: Preparado para Auto-Login URL e tratamento de Erro 132018
 */
class HubAppWabaClient {

    public static function getValidNumber($clientId) {
        $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_waba')->pluck('value', 'setting');
        $fieldId = (int)$config['whatsapp_field_id'];
        
        $num = ($fieldId > 0) ? Capsule::table('tblcustomfieldsvalues')->where('fieldid', $fieldId)->where('relid', $clientId)->value('value') : '';
        if (empty(trim($num))) $num = Capsule::table('tblclients')->where('id', $clientId)->value('phonenumber');

        $cleanNumber = preg_replace('/\D/', '', $num);
        if (strlen($cleanNumber) >= 10 && strlen($cleanNumber) <= 11) {
            if (substr($cleanNumber, 0, 1) === '0') $cleanNumber = substr($cleanNumber, 1);
            if (substr($cleanNumber, 0, 2) !== '55') $cleanNumber = '55' . $cleanNumber;
        }
        return $cleanNumber;
    }

    public static function sendTemplate($clientId, $templateName, $params = []) {
        $number = self::getValidNumber($clientId);
        if (empty($number) || empty($templateName)) return false;
        return self::execute($number, $templateName, $params);
    }

    public static function sendTemplateByNumber($number, $templateName, $params = []) {
        $cleanNumber = preg_replace('/\D/', '', $number);
        if (empty($cleanNumber) || empty($templateName)) return false;
        return self::execute($cleanNumber, $templateName, $params);
    }

    private static function execute($to, $templateName, $params) {
        $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_waba')->pluck('value', 'setting');
        
        // Sanitização de Parâmetros (Correção do Erro 132018)
        $bodyParameters = [];
        foreach ($params as $value) {
            $cleanValue = preg_replace('/[\r\n\t]+/', ' ', (string)$value);
            $cleanValue = preg_replace('/ {4,}/', ' ', $cleanValue);
            
            $bodyParameters[] = [
                "type" => "text", 
                "text" => trim($cleanValue)
            ];
        }

        $components = [
            [
                "type" => "body",
                "parameters" => $bodyParameters
            ]
        ];

        if ($config['use_header'] == 'on' && !empty($config['header_text'])) {
            $components[] = [
                "type" => "header",
                "parameters" => [["type" => "text", "text" => (string)$config['header_text']]]
            ];
        }

        if ($config['use_footer'] == 'on' && !empty($config['footer_text'])) {
            $components[] = [
                "type" => "footer",
                "parameters" => [["type" => "text", "text" => (string)$config['footer_text']]]
            ];
        }

        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "template",
            "template" => [
                "name" => $templateName,
                "language" => ["code" => $config['language_code'] ?: 'pt_BR'],
                "components" => $components
            ]
        ];

        $token = trim(str_replace('Bearer ', '', $config['api_token']));
        $endpoint = rtrim($config['api_endpoint'], '/') . '/messages';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token, 
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}