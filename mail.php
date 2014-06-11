<?php

require 'vendor/autoload.php';

function enviaEmailMultiDestinoCaixa(
    $assunto,
    $corpo,
    $destinatarios,
    $emissor = null,
    $destinatariosCc = null,
    $destinatariosBcc = null
) {
    /*
     * configurações do php para mail
     */
    ini_set('SMTP', 'sp7877sr108');
    ini_set('smtp_port', 25);

    if ($emissor == null) {
        $emissor = 'SICOM@mail.caixa';
    }

    ini_set('sendmail_from', $emissor);

    /*
     * grupo destinatários
     */
    $destino  =   '';
    foreach ($destinatarios as $valor) {
        $destino  .=  $valor . '@mail.caixa,';
    }
    // tirar a última virgula
    $destino = substr($destino, 0, -1);

    /*
     * configurações de cabeçalho extra
     */
    $cabecalhoExtra = '';
    // Cabecalho extra copia adicional
    if ( ! empty($destinatariosCc) ) {
        $destinoCc = '';
        foreach ($destinatariosCc as $valor) {
            $destinoCc .=  $valor . '@mail.caixa,';
        }
        // tirar a última virgula
        $destinoCc = substr($destinoCc, 0, -1);

        $cabecalhoExtra .= 'Cc: ' . $destinoCc . _quebraLinha();
    }

    // Cabecalho extra copia oculta
    if ( ! empty($destinatariosBcc) ) {
        $destinoBcc = '';
        foreach ($destinatariosBcc as $valor) {
            $destinoBcc  .=  $valor . '@mail.caixa,';
        }
        // tirar a última virgula
        $destinoBcc = substr($destinoBcc, 0, -1);

        $cabecalhoExtra .= 'Bcc: ' . $destinoBcc . _quebraLinha();
    }
    // Cabecalho extra Define HTML
    $cabecalhoExtra .=  'MIME-Version: 1.0' . _quebraLinha();
    // Cabecalho extra Define HTML
    $cabecalhoExtra .=  'Content-type: text/html; charset=utf-8' . _quebraLinha();
    // Cabecalho extra From:
    $cabecalhoExtra .=  'From: ' . $emissor . _quebraLinha();
    // Cabecalho extra Replay-To:
    $cabecalhoExtra .=  'Reply-To: ' . $destino . _quebraLinha();
    // Cabecalho extra X-Mailer: <- indica qual cliente formatou a mensagem
    $cabecalhoExtra .=  'X-Mailer: PHP/' . phpversion();

    return mail(
         // To: <- quando mais de um, separa com virgula
        $destino,
        // Assunto
        $assunto,
         // Mensagem
        $corpo,
        // CabecalhoExtra
        $cabecalhoExtra
    );
} // static public function enviaEmailMultiDestino()

function _quebraLinha()
{
    $eol = '';

    if (strtoupper(substr(PHP_OS,0,3)=='WIN')) {
        $eol="\r\n";
    } elseif (strtoupper(substr(PHP_OS,0,3)=='MAC')) {
        $eol="\r";
    } else {
        $eol="\n";
    }

    return $eol;
}

/*
$assunto = 'Teste de assunto';
$corpo = 'Mensagem do e-mail';
$destinatarios = array();
$destinatarios[] = 'c090762';
$emissor = 'SICOM';
$destinatariosCc = null;
$destinatariosBcc = null;

//enviaEmailMultiDestinoCaixa($assunto, $corpo, $destinatarios, $emissor, $destinatariosCc, $destinatariosBcc);

$mensagem = array(
    'assunto'          => 'Teste de assunto',
    'corpo'            => 'Mensagem do e-mail',
    'destinatarios'    => array('c090762', 'c090762'),
    'emissor'          => 'SICOM',
    'destinatariosCc'  => array('c090762', 'c090762'),
    'destinatariosBcc' => array('c090762', 'c090762')
);

*/

//$json = json_encode($mensagem);

//echo $json;

//var_dump(json_decode($json, true));

if ((strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') && ($_SERVER['REMOTE_ADDR'] == '10.5.16.109')) {

    if (!empty($_POST)) {  // if received any post data
        //envia a mensagem de e-mail
        $mensagem = json_decode($_POST['mensagem'], true);

        sendEmail($mensagem);
    }

    return;
}

function sendEmail($mensagem)
{
    if (array_key_exists('template', $mensagem)) {

        // set up Twig
        $loader = new Twig_Loader_Filesystem('templates');
        $twig = new Twig_Environment($loader, array(
            //'cache' => 'cache',
        ));

        // Create the Transport
        $transport = Swift_SmtpTransport::newInstance('sp7877sr108', 25);

        // Create the Mailer using your created Transport
        $mailer = Swift_Mailer::newInstance($transport);

        // Load the template in
        $templateFile = $mensagem['template'] . '.html.twig';
        $templateContent = $twig->loadTemplate($templateFile);

        switch ($mensagem['template']) {

            case 'assinatura_ata_participantes':
                emailParticipanteAssinaturaAta($mensagem, $templateContent, $mailer);
                break;

            case 'nova_observacao':
                emailNovaObservacao($mensagem, $templateContent, $mailer);
                break;
        }
    }
}

function emailParticipanteAssinaturaAta($mensagem, $templateContent, $mailer)
{
    if (($mensagem['template'] == 'assinatura_ata_participantes') &&
        (count($mensagem['participantes']) > 0)) {

        foreach ($mensagem['participantes'] as $participante) {

            // Render the whole template including any layouts etc
            $body = $templateContent->render(
                array(
                    'participante'        => $participante,
                    'id_comite'           => $mensagem['id_comite'],
                    'file_comite'         => $mensagem['file_comite'],
                    'nome_comite'         => $mensagem['nome_comite'],
                    'data_comite'         => $mensagem['data_comite'],
                    'numero_ata'          => $mensagem['numero_ata'],
                    'ano_ata'             => $mensagem['ano_ata'],
                    'matricula_remetente' => $mensagem['matricula_remetente'],
                    'nome_remetente'      => $mensagem['nome_remetente']
                    )
                );

            // Create the message
            $message = Swift_Message::newInstance()
                // Give the message a subject
                ->setSubject($mensagem['assunto'])
                // Set the From address with an associative array
                ->setFrom(array('SICOM@mail.caixa' => 'SICOM - Sistema de Controle de Comitês'))
                // Set the To addresses with an associative array
                ->setTo(array($participante['matricula'].'@mail.caixa' => $participante['nome']))
                // Give it a body
                ->setBody($body, 'text/html')
                ;

            // Send the message
            $result = $mailer->send($message);

            gravaLog(
                $mensagem['template'],
                'E-mail enviado com sucesso para o usuário ' .
                    $participante['matricula'] .
                    ' pelo remetente ' .
                    $mensagem['matricula_remetente'],
                'Erro no envio de e-mail enviado para o usuário ' .
                    $participante['matricula'] .
                    ' pelo remetente ' .
                    $mensagem['matricula_remetente'],
                $result
                );
        }
    }
}

function gravaLog($nome, $info, $warning, $result)
{
    $log = new Monolog\Logger($nome);

    if ($result) {
        $log->pushHandler(new Monolog\Handler\StreamHandler('logs/app.log', Monolog\Logger::DEBUG));
        $log->addInfo($info);
    } else {
        $log->pushHandler(new Monolog\Handler\StreamHandler('logs/app.log', Monolog\Logger::WARNING));
        $log->addWarning($warning);
    }
}

function emailNovaObservacao($mensagem, $templateContent, $mailer)
{
    $comite       = $mensagem['comite'];
    $unidade      = $mensagem['unidade'];
    $participante = $mensagem['participante'];

    $assunto = 'SICOM - Nova Observação Registrada - ' .
        $comite['nome'] .
        ' - ' .
        $comite['numero_ata'] .
        '/' .
        $comite['ano_ata'];

    // Render the whole template including any layouts etc
    $body = $templateContent->render(
        array(
            'assunto'      => $assunto,
            'unidade'      => $unidade,
            'comite'       => $comite,
            'participante' => $participante
        )
    );

    // Create the message
    $message = Swift_Message::newInstance()
        // Give the message a subject
        ->setSubject($assunto)
        // Set the From address with an associative array
        ->setFrom(array('SICOM@mail.caixa' => 'SICOM - Sistema de Controle de Comitês'))
        // Set the To addresses with an associative array
        ->setTo(array($unidade['sigla'].'@mail.caixa' => $unidade['nome']))
        //e-mail de log
        ->setBcc(array('c090762@mail.caixa' => 'Rodrigo Prado de Jesus'))
        // Give it a body
        ->setBody($body, 'text/html')
    ;

    // Send the message
    $result = $mailer->send($message);

    gravaLog(
        $mensagem['template'],
        'E-mail enviado com sucesso para a unidade ' .
        $unidade['codigo'],
        'Erro no envio de e-mail enviado para a unidade ' .
        $unidade['codigo'],
        $result
    );
}

/*
$mensagem = array(
  'template' => 'nova_observacao',
  'unidade' =>
    array(
      'codigo' => 5482,
      'nome'  => 'GN de Risco de Ativos de Terceiros',
      'sigla' => 'GERAT'
      ),
  'comite' =>
    array(
      'id' => 57,
      'nome' => 'Comitê de Planejamento e Gestão',
      'numero_ata' => 22,
      'ano_ata' => 2014,
      'data' => new \DateTime('2014-06-09 00:00:00')
      ),
  'participante' =>
    array(
      'matricula' => 'c090762',
      'nome' => 'Rodrigo Prado de Jesus'
      )
);

// set up Twig
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader, array(
    //'cache' => 'cache',
));
// Create the Transport
$transport = Swift_SmtpTransport::newInstance('sp7877sr108', 25);
// Create the Mailer using your created Transport
$mailer = Swift_Mailer::newInstance($transport);
// Load the template in
$templateFile = 'nova_observacao.html.twig';
$templateContent = $twig->loadTemplate($templateFile);
//emailNovaObservacao($mensagem, $templateContent, $mailer);
*/
