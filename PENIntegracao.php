<?php

class PENIntegracao extends SeiIntegracao {

    private static $strDiretorio;

    public function getNome() {
        return 'M�dulo de Integra��o com o Barramento PEN';
    }

    public function getVersao() {
        return '1.1.0';
    }

    public function getInstituicao() {
        return 'MPDG - Minist�rio do Planejamento, Desenvolvimento e Gest�o';
    }

    public function montarBotaoProcesso(ProcedimentoAPI $objSeiIntegracaoDTO) {

        $objProcedimentoDTO = new ProcedimentoDTO();
        $objProcedimentoDTO->setDblIdProcedimento($objSeiIntegracaoDTO->getIdProcedimento());
        $objProcedimentoDTO->retTodos();
        
        $objProcedimentoRN = new ProcedimentoRN();
        $objProcedimentoDTO = $objProcedimentoRN->consultarRN0201($objProcedimentoDTO);

        $objSessaoSEI = SessaoSEI::getInstance();
        $objPaginaSEI = PaginaSEI::getInstance();
        $strAcoesProcedimento = "";

        $dblIdProcedimento = $objProcedimentoDTO->getDblIdProcedimento();
        $numIdUsuario = SessaoSEI::getInstance()->getNumIdUsuario();
        $numIdUnidadeAtual = SessaoSEI::getInstance()->getNumIdUnidadeAtual();
        $objInfraParametro = new InfraParametro(BancoSEI::getInstance());       
        
        //Verifica se o processo encontra-se aberto na unidade atual
        $objAtividadeRN = new AtividadeRN();
        $objPesquisaPendenciaDTO = new PesquisaPendenciaDTO();
        $objPesquisaPendenciaDTO->setDblIdProtocolo($dblIdProcedimento);
        $objPesquisaPendenciaDTO->setNumIdUsuario($numIdUsuario);
        $objPesquisaPendenciaDTO->setNumIdUnidade($numIdUnidadeAtual);
        $objPesquisaPendenciaDTO->setStrSinMontandoArvore('N');
        $arrObjProcedimentoDTO = $objAtividadeRN->listarPendenciasRN0754($objPesquisaPendenciaDTO);
        $bolFlagAberto = count($arrObjProcedimentoDTO) == 1;

        //Verifica��o da Restri��o de Acesso � Funcionalidade
        $bolAcaoExpedirProcesso = $objSessaoSEI->verificarPermissao('pen_procedimento_expedir');

        $objExpedirProcedimentoRN = new ExpedirProcedimentoRN();
        $objProcedimentoDTO = $objExpedirProcedimentoRN->consultarProcedimento($dblIdProcedimento);

        $bolProcessoEstadoNormal = !in_array($objProcedimentoDTO->getStrStaEstadoProtocolo(), array(
                    ProtocoloRN::$TE_PROCEDIMENTO_SOBRESTADO,
                    ProtocoloRN::$TE_PROCEDIMENTO_BLOQUEADO
        ));

        //Apresenta o bot�o de expedir processo
        if ($bolFlagAberto && $bolAcaoExpedirProcesso && $bolProcessoEstadoNormal && $objProcedimentoDTO->getStrStaNivelAcessoGlobalProtocolo() != ProtocoloRN::$NA_SIGILOSO) {
            $numTabBotao = $objPaginaSEI->getProxTabBarraComandosSuperior();
            $strAcoesProcedimento .= '<a id="validar_expedir_processo" href="' . $objPaginaSEI->formatarXHTML($objSessaoSEI->assinarLink('controlador.php?acao=pen_procedimento_expedir&acao_origem=procedimento_visualizar&acao_retorno=arvore_visualizar&id_procedimento=' . $dblIdProcedimento . '&arvore=1')) . '" tabindex="' . $numTabBotao . '" class="botaoSEI"><img class="infraCorBarraSistema" src="' . $this->getDiretorioImagens() . '/pen_expedir_procedimento.gif" alt="Tramitar Externamente" title="Tramitar Externamente" /></a>';
        }

        if($bolAcaoExpedirProcesso){
            //Apresenta o bot�o da p�gina de recibos
            $strAcoesProcedimento .= '<a href="' . $objSessaoSEI->assinarLink('controlador.php?acao=pen_procedimento_estado&acao_origem=procedimento_visualizar&acao_retorno=arvore_visualizar&id_procedimento=' . $dblIdProcedimento . '&arvore=1') . '" tabindex="' . $numTabBotao . '" class="botaoSEI">';
            $strAcoesProcedimento .= '<img class="infraCorBarraSistema" src="' . $this->getDiretorioImagens() . '/pen_consultar_recibos.png" alt="Consultar Recibos" title="Consultar Recibos"/>';
            $strAcoesProcedimento .= '</a>';
        }
        //Apresenta o bot�o de cancelar tr�mite
        $objAtividadeDTO = $objExpedirProcedimentoRN->verificarProcessoEmExpedicao($objSeiIntegracaoDTO->getIdProcedimento());

        if ($objAtividadeDTO && $objAtividadeDTO->getNumIdTarefa() == ProcessoEletronicoRN::obterIdTarefaModulo(ProcessoEletronicoRN::$TI_PROCESSO_ELETRONICO_PROCESSO_EXPEDIDO)) {

            $strAcoesProcedimento .= '<a href="' . $objPaginaSEI->formatarXHTML($objSessaoSEI->assinarLink('controlador.php?acao=pen_procedimento_cancelar_expedir&acao_origem=procedimento_visualizar&acao_retorno=arvore_visualizar&id_procedimento=' . $dblIdProcedimento . '&arvore=1')) . '" tabindex="' . $numTabBotao . '" class="botaoSEI">';
            $strAcoesProcedimento .= '<img class="infraCorBarraSistema" src="' . $this->getDiretorioImagens() . '/sei_desanexar_processo.gif" alt="Cancelar Tramita��o Externa" title="Cancelar Tramita��o Externa" />';
            $strAcoesProcedimento .= '</a>';
         } 
       
        return array($strAcoesProcedimento);
    }

    public function montarIconeControleProcessos($arrObjProcedimentoAPI = array()) {
        
        $arrStrIcone = array();
        $arrDblIdProcedimento = array();

        foreach ($arrObjProcedimentoAPI as $ObjProcedimentoAPI) {
            $arrDblIdProcedimento[] = $ObjProcedimentoAPI->getIdProcedimento();
        }

        $objProcedimentoDTO = new ProcedimentoDTO();
        $objProcedimentoDTO->setDblIdProcedimento($arrDblIdProcedimento, InfraDTO::$OPER_IN);
        $objProcedimentoDTO->retDblIdProcedimento();
        $objProcedimentoDTO->retStrStaEstadoProtocolo();

        $objProcedimentoBD = new ProcedimentoBD(BancoSEI::getInstance());
        $arrObjProcedimentoDTO = $objProcedimentoBD->listar($objProcedimentoDTO);

        if (!empty($arrObjProcedimentoDTO)) {

            foreach ($arrObjProcedimentoDTO as $objProcedimentoDTO) {

                $dblIdProcedimento = $objProcedimentoDTO->getDblIdProcedimento();
                $objPenProtocoloDTO = new PenProtocoloDTO();
                $objPenProtocoloDTO->setDblIdProtocolo($dblIdProcedimento);
                $objPenProtocoloDTO->retStrSinObteveRecusa();
                $objPenProtocoloDTO->setNumMaxRegistrosRetorno(1);

                $objProtocoloBD = new ProtocoloBD(BancoSEI::getInstance());
                $objPenProtocoloDTO = $objProtocoloBD->consultar($objPenProtocoloDTO);

                if (!empty($objPenProtocoloDTO) && $objPenProtocoloDTO->getStrSinObteveRecusa() == 'S') {
                    $arrStrIcone[$dblIdProcedimento] = array('<img src="' . $this->getDiretorioImagens() . '/pen_tramite_recusado.png" title="Um tr�mite para esse processo foi recusado" />');
                }

            }
        }

        return $arrStrIcone;
    }
    
    public function montarIconeProcesso(ProcedimentoAPI $objProcedimentoAP) {
        $dblIdProcedimento = $objProcedimentoAP->getIdProcedimento();

        $objArvoreAcaoItemAPI = new ArvoreAcaoItemAPI();
        $objArvoreAcaoItemAPI->setTipo('MD_TRAMITE_PROCESSO');
        $objArvoreAcaoItemAPI->setId('MD_TRAMITE_PROC_' . $dblIdProcedimento);
        $objArvoreAcaoItemAPI->setIdPai($dblIdProcedimento);
        $objArvoreAcaoItemAPI->setTitle('Um tr�mite para esse processo foi recusado');
        $objArvoreAcaoItemAPI->setIcone($this->getDiretorioImagens() . '/pen_tramite_recusado.png');

        $objArvoreAcaoItemAPI->setTarget(null);
        $objArvoreAcaoItemAPI->setHref('javascript:alert(\'Um tr�mite para esse processo foi recusado\');');

        $objArvoreAcaoItemAPI->setSinHabilitado('S');
        
        $objProcedimentoDTO = new ProcedimentoDTO();
        $objProcedimentoDTO->setDblIdProcedimento($dblIdProcedimento);
        $objProcedimentoDTO->retDblIdProcedimento();
        $objProcedimentoDTO->retStrStaEstadoProtocolo();
        
        $objProcedimentoBD = new ProcedimentoBD(BancoSEI::getInstance());
        $arrObjProcedimentoDTO = $objProcedimentoBD->consultar($objProcedimentoDTO);
        
        if (!empty($arrObjProcedimentoDTO)) {
            $dblIdProcedimento = $objProcedimentoDTO->getDblIdProcedimento();
            $objPenProtocoloDTO = new PenProtocoloDTO();
            $objPenProtocoloDTO->setDblIdProtocolo($dblIdProcedimento);
            $objPenProtocoloDTO->retStrSinObteveRecusa();
            $objPenProtocoloDTO->setNumMaxRegistrosRetorno(1);

            $objProtocoloBD = new ProtocoloBD(BancoSEI::getInstance());
            $objPenProtocoloDTO = $objProtocoloBD->consultar($objPenProtocoloDTO);

            if (!empty($objPenProtocoloDTO) && $objPenProtocoloDTO->getStrSinObteveRecusa() == 'S') {
                $arrObjArvoreAcaoItemAPI[] = $objArvoreAcaoItemAPI;
            }
        } else {
            return array();
        }

        return $arrObjArvoreAcaoItemAPI;
    }

    public function montarIconeAcompanhamentoEspecial($arrObjProcedimentoDTO) {
        
    }

    public function getDiretorioImagens() {
        return static::getDiretorio() . '/imagens';
    }

    public function montarMensagemProcesso(ProcedimentoAPI $objProcedimentoAPI) {

        $objExpedirProcedimentoRN = new ExpedirProcedimentoRN();
        $objAtividadeDTO = $objExpedirProcedimentoRN->verificarProcessoEmExpedicao($objProcedimentoAPI->getIdProcedimento());

        if ($objAtividadeDTO && $objAtividadeDTO->getNumIdTarefa() == ProcessoEletronicoRN::obterIdTarefaModulo(ProcessoEletronicoRN::$TI_PROCESSO_ELETRONICO_PROCESSO_EXPEDIDO)) {

            $objAtributoAndamentoDTO = new AtributoAndamentoDTO();
            $objAtributoAndamentoDTO->setStrNome('UNIDADE_DESTINO');
            $objAtributoAndamentoDTO->setNumIdAtividade($objAtividadeDTO->getNumIdAtividade());
            $objAtributoAndamentoDTO->retStrValor();

            $objAtributoAndamentoRN = new AtributoAndamentoRN();
            $objAtributoAndamentoDTO = $objAtributoAndamentoRN->consultarRN1366($objAtributoAndamentoDTO);

            return sprintf('Processo em tr�mite externo para "%s".', $objAtributoAndamentoDTO->getStrValor());
        }
    }

    public static function getDiretorio() {
       
        
        $arrConfig = ConfiguracaoSEI::getInstance()->getValor('SEI', 'Modulos');
        $strModulo = $arrConfig['PENIntegracao'];
        
        return "modulos/".$strModulo;
    }

    public function processarControlador($strAcao) {
        switch ($strAcao) {
            case 'pen_procedimento_expedir':
                require_once dirname(__FILE__) . '/pen_procedimento_expedir.php';
                return true;
            //TODO: Alterar nome do recurso para pen_procedimento_expedir_unidade_sel
            case 'pen_unidade_sel_expedir_procedimento':
                require_once dirname(__FILE__) . '/pen_unidade_sel_expedir_procedimento.php';
                return true;

            case 'pen_procedimento_processo_anexado':
                require_once dirname(__FILE__) . '/pen_procedimento_processo_anexado.php';
                return true;

            case 'pen_procedimento_cancelar_expedir':
                require_once dirname(__FILE__) . '/pen_procedimento_cancelar_expedir.php';
                return true;

            case 'pen_procedimento_expedido_listar':
                require_once dirname(__FILE__) . '/pen_procedimento_expedido_listar.php';
                return true;

            case 'pen_map_tipo_documento_envio_listar':
            case 'pen_map_tipo_documento_envio_excluir':
            case 'pen_map_tipo_documento_envio_desativar':
            case 'pen_map_tipo_documento_envio_ativar':
                require_once dirname(__FILE__) . '/pen_map_tipo_documento_envio_listar.php';
                return true;

            case 'pen_map_tipo_documento_envio_cadastrar':
            case 'pen_map_tipo_documento_envio_visualizar':
                require_once dirname(__FILE__) . '/pen_map_tipo_documento_envio_cadastrar.php';
                return true;

            case 'pen_map_tipo_documento_recebimento_listar':
            case 'pen_map_tipo_documento_recebimento_excluir':
                require_once dirname(__FILE__) . '/pen_map_tipo_documento_recebimento_listar.php';
                return true;

            case 'pen_map_tipo_documento_recebimento_cadastrar':
            case 'pen_map_tipo_documento_recebimento_visualizar':
                require_once dirname(__FILE__) . '/pen_map_tipo_documento_recebimento_cadastrar.php';
                return true;

            case 'apensados_selecionar_expedir_procedimento':
                require_once dirname(__FILE__) . '/apensados_selecionar_expedir_procedimento.php';
                return true;

            case 'pen_procedimento_estado':
                require_once dirname(__FILE__) . '/pen_procedimento_estado.php';
                return true;
            
            // Mapeamento de Hip�teses Legais de Envio
            case 'pen_map_hipotese_legal_envio_cadastrar':
            case 'pen_map_hipotese_legal_envio_visualizar':
                require_once dirname(__FILE__) . '/pen_map_hipotese_legal_envio_cadastrar.php';
                return true;
            
            case 'pen_map_hipotese_legal_envio_listar':
            case 'pen_map_hipotese_legal_envio_excluir':
                require_once dirname(__FILE__) . '/pen_map_hipotese_legal_envio_listar.php';
                return true;
            
            // Mapeamento de Hip�teses Legais de Recebimento
            case 'pen_map_hipotese_legal_recebimento_cadastrar':
            case 'pen_map_hipotese_legal_recebimento_visualizar':
                require_once dirname(__FILE__) . '/pen_map_hipotese_legal_recebimento_cadastrar.php';
                return true;
            
            case 'pen_map_hipotese_legal_recebimento_listar':
            case 'pen_map_hipotese_legal_recebimento_excluir':
                require_once dirname(__FILE__) . '/pen_map_hipotese_legal_recebimento_listar.php';
                return true;
            
            case 'pen_map_hipotese_legal_padrao_cadastrar':
            case 'pen_map_hipotese_legal_padrao_visualizar':
                require_once dirname(__FILE__) . '/pen_map_hipotese_legal_padrao_cadastrar.php';
                return true;
                
            case 'pen_map_unidade_cadastrar':
            case 'pen_map_unidade_visualizar':
                require_once dirname(__FILE__) . '/pen_map_unidade_cadastrar.php';
                return true;
                
            case 'pen_map_unidade_listar':
            case 'pen_map_unidade_excluir':
                require_once dirname(__FILE__) . '/pen_map_unidade_listar.php';
                return true;
                
            case 'pen_parametros_configuracao':
            case 'pen_parametros_configuracao_salvar':
                require_once dirname(__FILE__) . '/pen_parametros_configuracao.php';
                return true;
        }

        return false;
    }

    public function processarControladorAjax($strAcao) {
        $xml = null;

        switch ($_GET['acao_ajax']) {

            case 'pen_unidade_auto_completar_expedir_procedimento':
                $arrObjEstruturaDTO = (array) ProcessoEletronicoINT::autoCompletarEstruturas($_POST['id_repositorio'], $_POST['palavras_pesquisa']);

                if (count($arrObjEstruturaDTO) > 0) {
                    $xml = InfraAjax::gerarXMLItensArrInfraDTO($arrObjEstruturaDTO, 'NumeroDeIdentificacaoDaEstrutura', 'Nome');
                } else {
                    return '<itens><item id="0" descricao="Unidade n�o Encontrada."></item></itens>';
                }
                break;

            case 'pen_apensados_auto_completar_expedir_procedimento':
                //TODO: Validar par�metros passados via ajax     
                $dblIdProcedimentoAtual = $_POST['id_procedimento_atual'];
                $numIdUnidadeAtual = SessaoSEI::getInstance()->getNumIdUnidadeAtual();
                $arrObjProcedimentoDTO = ProcessoEletronicoINT::autoCompletarProcessosApensados($dblIdProcedimentoAtual, $numIdUnidadeAtual, $_POST['palavras_pesquisa']);
                $xml = InfraAjax::gerarXMLItensArrInfraDTO($arrObjProcedimentoDTO, 'IdProtocolo', 'ProtocoloFormatadoProtocolo');
                break;

            case 'pen_procedimento_expedir_validar':
                require_once dirname(__FILE__) . '/pen_procedimento_expedir_validar.php';
                break;
        }

        return $xml;
    }

}