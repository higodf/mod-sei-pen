<?php

require_once dirname(__FILE__) . '/../../../SEI.php';

error_reporting(E_ALL);

class PendenciasTramiteRN extends InfraRN {

    const TIMEOUT_SERVICO_PENDENCIAS = 300;
    const RECUPERAR_TODAS_PENDENCIAS = true;

    private static $instance = null;
    private $strEnderecoServicoPendencias = null;
    private $strLocalizacaoCertificadoDigital = null;
    private $strSenhaCertificadoDigital = null;

    protected function inicializarObjInfraIBanco(){
        return BancoSEI::getInstance();
    }

    public static function getInstance() {
        if (self::$instance == null) {
            PENIntegracao::validarCompatibilidadeBanco();
            self::$instance = new PendenciasTramiteRN(ConfiguracaoSEI::getInstance(), SessaoSEI::getInstance(), BancoSEI::getInstance(), LogSEI::getInstance());
        }

        return self::$instance;
    }

    public function __construct() {
        $objPenParametroRN = new PenParametroRN();

        $this->strLocalizacaoCertificadoDigital = $objPenParametroRN->getParametro('PEN_LOCALIZACAO_CERTIFICADO_DIGITAL');
        $this->strEnderecoServicoPendencias = $objPenParametroRN->getParametro('PEN_ENDERECO_WEBSERVICE_PENDENCIAS');
        //TODO: Urgente - Remover senha do certificado de autenticao dos servios do PEN da tabela de par�metros
        $this->strSenhaCertificadoDigital = $objPenParametroRN->getParametro('PEN_SENHA_CERTIFICADO_DIGITAL');

        if (InfraString::isBolVazia($this->strEnderecoServicoPendencias)) {
            throw new InfraException('Endere�o do servi�o de pend�ncias de tr�mite do Processo Eletr�nico Nacional (PEN) n�o informado.');
        }

        if (!@file_get_contents($this->strLocalizacaoCertificadoDigital)) {
            throw new InfraException("Certificado digital de autentica��o do servi�o de integra��o do Processo Eletr�nico Nacional(PEN) n�o encontrado.");
        }

        if (InfraString::isBolVazia($this->strSenhaCertificadoDigital)) {
            throw new InfraException('Dados de autentica��o do servi�o de integra��o do Processo Eletr�nico Nacional(PEN) n�o informados.');
        }
    }

    public function monitorarPendencias() {
        try{
            ini_set('max_execution_time','0');
            ini_set('memory_limit','-1');

            InfraDebug::getInstance()->setBolLigado(true);
            InfraDebug::getInstance()->setBolDebugInfra(false);
            InfraDebug::getInstance()->setBolEcho(true);
            InfraDebug::getInstance()->limpar();

            PENIntegracao::validarCompatibilidadeModulo();

            $objPenParametroRN = new PenParametroRN();
            SessaoSEI::getInstance(false)->simularLogin('SEI', null, null, $objPenParametroRN->getParametro('PEN_UNIDADE_GERADORA_DOCUMENTO_RECEBIDO'));

            $mensagemInicioMonitoramento = 'Iniciando servi�o de monitoramento de pend�ncias de tr�mites de processos';
            LogSEI::getInstance()->gravar($mensagemInicioMonitoramento, LogSEI::$INFORMACAO);
            $this->gravarLogDebug($mensagemInicioMonitoramento, 0, true);

            $numIdTramiteRecebido = 0;
            $strStatusTramiteRecebido = '';
            $numQuantidadeErroTramite = 0;
            $arrQuantidadeErrosTramite = array();

            while (true) {
                try {
                    PENIntegracao::validarCompatibilidadeBanco();

                    $this->gravarLogDebug('Recuperando lista de pend�ncias do PEN', 1);
                    $arrObjPendenciasDTO = $this->obterPendenciasTramite();
                    foreach ($arrObjPendenciasDTO as $objPendenciaDTO) {
                        $mensagemLog = sprintf(">>> Enviando pend?ncia %d (status %s) para fila de processamento",
                            $objPendenciaDTO->getNumIdentificacaoTramite(), $objPendenciaDTO->getStrStatus());
                        $this->gravarLogDebug($mensagemLog, 3, true);
                        $this->enviarPendenciaFilaProcessamento($objPendenciaDTO);
                    }

                } catch(ModuloIncompativelException $e) {
                    //Registra a falha no log do sistema e reinicia o ciclo de requisi��o e
                    //sai loop de eventos para finalizar o script e subir uma nova vers�o atualizada
                    LogSEI::getInstance()->gravar(InfraException::inspecionar($e));
                    $this->gravarLogDebug(InfraException::inspecionar($e));
                } catch (Exception $e) {
                    //Apenas registra a falha no log do sistema e reinicia o ciclo de requisi��o
                    LogSEI::getInstance()->gravar(InfraException::inspecionar($e));
                    $this->gravarLogDebug(InfraException::inspecionar($e));
                } finally {
                    $this->gravarLogDebug("Reiniciando monitoramento de pend?ncias", 1);
                    sleep(5);
                }
            }
        }
        catch(Exception $e) {
            InfraDebug::getInstance()->setBolLigado(false);
            InfraDebug::getInstance()->setBolDebugInfra(false);
            InfraDebug::getInstance()->setBolEcho(false);
            LogSEI::getInstance()->gravar(InfraException::inspecionar($e));
            throw $e;
        }
    }

    private function configurarRequisicao()
    {
        $curl = curl_init($this->strEnderecoServicoPendencias);
        curl_setopt($curl, CURLOPT_URL, $this->strEnderecoServicoPendencias);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT_SERVICO_PENDENCIAS);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_SSLCERT, $this->strLocalizacaoCertificadoDigital);
        curl_setopt($curl, CURLOPT_SSLCERTPASSWD, $this->strSenhaCertificadoDigital);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT_SERVICO_PENDENCIAS);
        return $curl;
    }


    /**
     * Fun��o para recuperar as pend�ncias de tr�mite que j� foram recebidas pelo servi�o de long pulling e n�o foram processadas com sucesso
     * @param  num $parNumIdTramiteRecebido
     * @return [type]                          [description]
     */
    private function obterPendenciasTramite()
    {
        //Obter todos os tr�mites pendentes antes de iniciar o monitoramento
        $arrPendenciasRetornadas = array();
        $objProcessoEletronicoRN = new ProcessoEletronicoRN();
        $arrObjPendenciasDTO = $objProcessoEletronicoRN->listarPendencias(self::RECUPERAR_TODAS_PENDENCIAS) or array();

        $this->gravarLogDebug("Recuperado todas pend�ncias de tr�mite do PEN: " . count($arrObjPendenciasDTO), 2);

        foreach ($arrObjPendenciasDTO as $objPendenciaDTO) {
            //Captura todas as pend�ncias e status retornadas para impedir duplicidade
            $arrPendenciasRetornadas[] = sprintf("%d-%s", $objPendenciaDTO->getNumIdentificacaoTramite(), $objPendenciaDTO->getStrStatus());
            yield $objPendenciaDTO;
        }

        //Obter demais pend�ncias do servi�o de long pulling
        $bolEncontrouPendencia = false;
        $numUltimoIdTramiteRecebido = 0;

        $arrObjPendenciasDTONovas = array();
        $this->gravarLogDebug("Iniciando monitoramento no servi�o de pend�ncias (long polling)", 2);

        do {
            $curl = $this->configurarRequisicao();
            try{
                $arrObjPendenciasDTONovas = array_unique($arrObjPendenciasDTONovas);
                curl_setopt($curl, CURLOPT_URL, $this->strEnderecoServicoPendencias . "?idTramiteDaPendenciaRecebida=" . $numUltimoIdTramiteRecebido);

                //A seguinte requisio ir aguardar a notificao do PEN sobre uma nova pendncia
                //ou at o lanamento da exceo de timeout definido pela infraestrutura da soluo
                //Ambos os comportamentos so esperados para a requisio abaixo.
                $this->gravarLogDebug(sprintf("Executando requisi��o de pend�ncia com IDT %d como offset", $numUltimoIdTramiteRecebido), 2);
                $strResultadoJSON = curl_exec($curl);

                if(curl_errno($curl)) {
                    if (curl_errno($curl) != 28)
                        throw new InfraException("Erro na requisi��o do servi�o de monitoramento de pend�ncias. Curl: " . curl_errno($curl));

                    $bolEncontrouPendencia = false;
                    $this->gravarLogDebug(sprintf("Timeout de monitoramento de %d segundos do servi�o de pend�ncias alcan�ado", self::TIMEOUT_SERVICO_PENDENCIAS), 2);
                }

                if(!InfraString::isBolVazia($strResultadoJSON)) {
                    $strResultadoJSON = json_decode($strResultadoJSON);

                    if(isset($strResultadoJSON->encontrou) && $strResultadoJSON->encontrou) {
                        $bolEncontrouPendencia = true;
                        $numUltimoIdTramiteRecebido = $strResultadoJSON->IDT;
                        $strUltimoStatusRecebido = $strResultadoJSON->status;
                        $strChavePendencia = sprintf("%d-%s", $strResultadoJSON->IDT, $strResultadoJSON->status);
                        $objPendenciaDTO = new PendenciaDTO();
                        $objPendenciaDTO->setNumIdentificacaoTramite($strResultadoJSON->IDT);
                        $objPendenciaDTO->setStrStatus($strResultadoJSON->status);

                        //N�o processo novamente as pend�ncias j� capturadas na consulta anterior ($objProcessoEletronicoRN->listarPendencias)
                        //Considera somente as novas identificadas pelo servi�o de monitoramento
                        if(!in_array($strChavePendencia, $arrPendenciasRetornadas)){
                            $arrObjPendenciasDTONovas[] = $strChavePendencia;
                            yield $objPendenciaDTO;

                        } elseif(in_array($strChavePendencia, $arrObjPendenciasDTONovas)) {
                            // Sleep adicionado para minimizar problema do servi�o de pend�ncia que retorna o mesmo c�digo e status
                            // in�meras vezes por causa de erro ainda n�o tratado
                            $mensagemErro = sprintf("Pend�ncia de tr�mite (IDT: %d / status: %s) enviado em duplicidade pelo servi�o de monitoramento de pend�ncias do PEN",
                                $numUltimoIdTramiteRecebido, $strUltimoStatusRecebido);
                            $this->gravarLogDebug($mensagemErro, 2);
                            throw new InfraException($mensagemErro);
                        } else {
                            $arrObjPendenciasDTONovas[] = $strChavePendencia;
                            $this->gravarLogDebug(sprintf("IDT %d desconsiderado por j� ter sido retornado na consulta inicial", $numUltimoIdTramiteRecebido), 2);
                        }
                    }
                }
            } catch (Exception $e) {
                $bolEncontrouPendencia = false;
                throw new InfraException("Erro processando monitoramento de pend�ncias de tr�mite de processos", $e);
            }finally{
                curl_close($curl);
            }

        } while($bolEncontrouPendencia);
    }

    private function enviarPendenciaFilaProcessamento($objPendencia)
    {
        if(isset($objPendencia)) {

        $client = new GearmanClient();
        $client->addServer("127.0.0.1", 4730);

        $numIDT = strval($objPendencia->getNumIdentificacaoTramite());
        switch ($objPendencia->getStrStatus()) {

            case ProcessoEletronicoRN::$STA_SITUACAO_TRAMITE_INICIADO:
                $client->addTaskBackground('enviarComponenteDigital', $numIDT, null, $numIDT);
                break;

            case ProcessoEletronicoRN::$STA_SITUACAO_TRAMITE_COMPONENTES_ENVIADOS_REMETENTE:
            case ProcessoEletronicoRN::$STA_SITUACAO_TRAMITE_METADADOS_RECEBIDO_DESTINATARIO:
            case ProcessoEletronicoRN::$STA_SITUACAO_TRAMITE_COMPONENTES_RECEBIDOS_DESTINATARIO:
                $client->addTaskBackground('receberProcedimento', $numIDT, null, $numIDT);
                break;

            case ProcessoEletronicoRN::$STA_SITUACAO_TRAMITE_RECIBO_ENVIADO_DESTINATARIO:
                $client->addTaskBackground('receberReciboTramite', $numIDT, null, $numIDT);
                break;

            case ProcessoEletronicoRN::$STA_SITUACAO_TRAMITE_RECUSADO:
                $client->addTaskBackground("receberTramitesRecusados", $numIDT, null, $numIDT);
            break;

            default:
                $strStatus = $objPendencia->getStrStatus();
                InfraDebug::getInstance()->gravar("Situa��o do tr�mite ($strStatus) n�o pode ser tratada.");
                break;
            }

            $client->runTasks();
        }
    }

    private function gravarLogDebug($strMensagem, $numIdentacao=0, $bolEcho=false)
    {
        $strDataLog = date("d/m/Y H:i:s");
        $strLog = sprintf("[%s] [MONITORAMENTO] %s %s", $strDataLog, str_repeat("\t", $numIdentacao), $strMensagem);
        InfraDebug::getInstance()->gravar($strLog);
        if(!InfraDebug::getInstance()->isBolEcho() && $bolEcho) echo sprintf("\n[%s] [MONITORAMENTO] %s", $strDataLog, $strMensagem);
    }

}

SessaoSEI::getInstance(false);
PendenciasTramiteRN::getInstance()->monitorarPendencias();
