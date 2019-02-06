<?php
require_once dirname(__FILE__).'/../web/Sip.php';

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(__DIR__ . '/../../infra/infra_php'),
    get_include_path(),
)));


class PenAtualizarSipRN extends InfraRN {

    protected $versaoMinRequirida = '1.30.0';
    const PARAMETRO_VERSAO_MODULO_ANTIGO = 'PEN_VERSAO_MODULO_SIP';
    const PARAMETRO_VERSAO_MODULO = 'VERSAO_MODULO_PEN';
    private $arrRecurso = array();
    private $arrMenu = array();

    public function __construct(){
        parent::__construct();
    }

    protected function inicializarObjInfraIBanco(){
        return BancoSip::getInstance();
    }

    /**
     * Inicia o script criando um contator interno do tempo de execu��o
     *
     * @return null
     */
    protected function inicializar($strTitulo) {

        session_start();
        SessaoSip::getInstance(false);

        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '-1');
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        ob_implicit_flush();

        $this->objDebug = InfraDebug::getInstance();
        $this->objDebug->setBolLigado(true);
        $this->objDebug->setBolDebugInfra(true);
        $this->objDebug->setBolEcho(true);
        $this->objDebug->limpar();

        $this->numSeg = InfraUtil::verificarTempoProcessamento();
        $this->logar($strTitulo);
    }

    protected function atualizarVersaoConectado() {
        try {
            $this->inicializar('INICIANDO ATUALIZACAO DO MODULO PEN NO SIP VERSAO 1.0.0');

            //testando se esta usando BDs suportados
            if (!(BancoSip::getInstance() instanceof InfraMySql) &&
                    !(BancoSip::getInstance() instanceof InfraSqlServer) &&
                    !(BancoSip::getInstance() instanceof InfraOracle)) {

                $this->finalizar('BANCO DE DADOS NAO SUPORTADO: ' . get_parent_class(BancoSip::getInstance()), true);
            }

            //testando permissoes de cria��es de tabelas
            $objInfraMetaBD = new InfraMetaBD(BancoSip::getInstance());

            if (count($objInfraMetaBD->obterTabelas('pen_sip_teste')) == 0) {
                BancoSip::getInstance()->executarSql('CREATE TABLE pen_sip_teste (id ' . $objInfraMetaBD->tipoNumero() . ' null)');
            }
            BancoSip::getInstance()->executarSql('DROP TABLE pen_sip_teste');


            $objInfraParametro = new InfraParametro(BancoSip::getInstance());

            // Aplica��o de scripts de atualiza��o de forma incremental
            // Aus�ncia de [break;] proposital para realizar a atualiza��o incremental de vers�es
            $strVersaoModuloPen = $objInfraParametro->getValor(self::PARAMETRO_VERSAO_MODULO, false) ?: $objInfraParametro->getValor(self::PARAMETRO_VERSAO_MODULO_ANTIGO, false);
            switch ($strVersaoModuloPen) {
                //case '' - Nenhuma vers�o instalada
                case '':      $this->instalarV100();
                case '1.0.0': $this->instalarV101();
                case '1.0.1': $this->instalarV102();
                case '1.0.2': $this->instalarV103();
                case '1.0.3': $this->instalarV104();
                case '1.0.4': $this->instalarV111();
                case '1.1.1': //N�o houve atualiza��o no banco de dados
                case '1.1.2': //N�o houve atualiza��o no banco de dados
                case '1.1.3': //N�o houve atualiza��o no banco de dados
                case '1.1.4': //N�o houve atualiza��o no banco de dados
                case '1.1.5': //N�o houve atualiza��o no banco de dados
                case '1.1.6': //N�o houve atualiza��o no banco de dados
                case '1.1.7': //N�o houve atualiza��o no banco de dados
                case '1.1.8': $this->instalarV119();
                case '1.1.9': $this->instalarV1110();
                case '1.1.10': $this->instalarV1111();
                case '1.1.11': $this->instalarV1112();
                case '1.1.12': $this->instalarV1113();

                break;
                default:
                    $this->finalizar('VERSAO DO M�DULO J� CONSTA COMO ATUALIZADA');
                    break;

            }

            $this->finalizar('FIM');
            InfraDebug::getInstance()->setBolDebugInfra(true);
        } catch (Exception $e) {

            InfraDebug::getInstance()->setBolLigado(false);
            InfraDebug::getInstance()->setBolDebugInfra(false);
            InfraDebug::getInstance()->setBolEcho(false);
            throw new InfraException('Erro atualizando VERSAO.', $e);
        }
    }

    /**
     * Finaliza o script informando o tempo de execu��o.
     *
     * @return null
     */
    protected function finalizar($strMsg=null, $bolErro=false){
        if (!$bolErro) {
          $this->numSeg = InfraUtil::verificarTempoProcessamento($this->numSeg);
          $this->logar('TEMPO TOTAL DE EXECUCAO: ' . $this->numSeg . ' s');
        }else{
          $strMsg = 'ERRO: '.$strMsg;
        }

        if ($strMsg!=null){
          $this->logar($strMsg);
        }

        InfraDebug::getInstance()->setBolLigado(false);
        InfraDebug::getInstance()->setBolDebugInfra(false);
        InfraDebug::getInstance()->setBolEcho(false);
        $this->numSeg = 0;
        die;
    }

    /**
     * Adiciona uma mensagem ao output para o usu�rio
     *
     * @return null
     */
    protected function logar($strMsg) {
        $this->objDebug->gravar($strMsg);
    }

    /**
     * Retorna o ID do sistema
     *
     * @return int
     */
    protected function getNumIdSistema($strSigla = 'SIP') {

        $objDTO = new SistemaDTO();
        $objDTO->setStrSigla($strSigla);
        $objDTO->setNumMaxRegistrosRetorno(1);
        $objDTO->retNumIdSistema();

        $objRN = new SistemaRN();
        $objDTO = $objRN->consultar($objDTO);

        return (empty($objDTO)) ? '0' : $objDTO->getNumIdSistema();
    }

    /**
     *
     * @return int C�digo do Menu
     */
    protected function getNumIdMenu($strMenu = 'Principal', $numIdSistema = 0) {

        $objDTO = new MenuDTO();
        $objDTO->setNumIdSistema($numIdSistema);
        $objDTO->setStrNome($strMenu);
        $objDTO->setNumMaxRegistrosRetorno(1);
        $objDTO->retNumIdMenu();

        $objRN = new MenuRN();
        $objDTO = $objRN->consultar($objDTO);

        if (empty($objDTO)) {
            throw new InfraException('Menu ' . $strMenu . ' n�o encontrado.');
        }

        return $objDTO->getNumIdMenu();
    }

    /**
     * Cria novo recurso no SIP
     * @return int C�digo do Recurso gerado
     */
    protected function criarRecurso($strNome, $strDescricao, $numIdSistema) {

        $objDTO = new RecursoDTO();
        $objDTO->setNumIdSistema($numIdSistema);
        $objDTO->setStrNome($strNome);
        $objDTO->setNumMaxRegistrosRetorno(1);
        $objDTO->retNumIdRecurso();

        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);

        if (empty($objDTO)) {

            $objDTO = new RecursoDTO();
            $objDTO->setNumIdRecurso(null);
            $objDTO->setStrDescricao($strDescricao);
            $objDTO->setNumIdSistema($numIdSistema);
            $objDTO->setStrNome($strNome);
            $objDTO->setStrCaminho('controlador.php?acao=' . $strNome);
            $objDTO->setStrSinAtivo('S');

            $objDTO = $objBD->cadastrar($objDTO);
        }

        $this->arrRecurso[] = $objDTO->getNumIdRecurso();

        return $objDTO->getNumIdRecurso();
    }

    protected function renomearRecurso($numIdSistema, $strNomeAtual, $strNomeNovo){

        $objRecursoDTO = new RecursoDTO();
        $objRecursoDTO->setBolExclusaoLogica(false);
        $objRecursoDTO->retNumIdRecurso();
        $objRecursoDTO->retStrCaminho();
        $objRecursoDTO->setNumIdSistema($numIdSistema);
        $objRecursoDTO->setStrNome($strNomeAtual);

        $objRecursoRN = new RecursoRN();
        $objRecursoDTO = $objRecursoRN->consultar($objRecursoDTO);

        if ($objRecursoDTO!=null){
            $objRecursoDTO->setStrNome($strNomeNovo);
            $objRecursoDTO->setStrCaminho(str_replace($strNomeAtual,$strNomeNovo,$objRecursoDTO->getStrCaminho()));
            $objRecursoRN->alterar($objRecursoDTO);
        }
    }

    protected function consultarRecurso($numIdSistema, $strNomeRecurso){

        $numIdRecurso = null;
        $objRecursoDTO = new RecursoDTO();
        $objRecursoDTO->setBolExclusaoLogica(false);
        $objRecursoDTO->setNumIdSistema($numIdSistema);
        $objRecursoDTO->setStrNome($strNomeRecurso);
        $objRecursoDTO->retNumIdRecurso();

        $objRecursoRN = new RecursoRN();
        $objRecursoDTO = $objRecursoRN->consultar($objRecursoDTO);

        if ($objRecursoDTO == null){
            throw new InfraException("Recurso com nome {$strNomeRecurso} n�o pode ser localizado.");
        }

        return $objRecursoDTO->getNumIdRecurso();
    }

    /**
     * Cria um menu
     *
     * @return int
     */
    protected function criarMenu($strRotulo = '', $numSequencia = 10, $numIdItemMenuPai = null, $numIdMenu = null, $numIdRecurso = null, $numIdSistema = 0) {

        $objDTO = new ItemMenuDTO();
        $objDTO->setNumIdItemMenuPai($numIdItemMenuPai);
        $objDTO->setNumIdSistema($numIdSistema);
        $objDTO->setStrRotulo($strRotulo);
        $objDTO->setNumIdRecurso($numIdRecurso);
        $objDTO->setNumMaxRegistrosRetorno(1);
        $objDTO->retNumIdItemMenu();

        $objBD = new ItemMenuBD(BancoSip::getInstance());
        $objDTO = $objBD->consultar($objDTO);

        if (empty($objDTO)) {

            $objDTO = new ItemMenuDTO();
            $objDTO->setNumIdMenu($numIdMenu);
            $objDTO->setNumIdMenuPai($numIdMenu);
            $objDTO->setNumIdItemMenu(null);
            $objDTO->setNumIdItemMenuPai($numIdItemMenuPai);
            $objDTO->setNumIdSistema($numIdSistema);
            $objDTO->setNumIdRecurso($numIdRecurso);
            $objDTO->setStrRotulo($strRotulo);
            $objDTO->setStrDescricao(null);
            $objDTO->setNumSequencia($numSequencia);
            $objDTO->setStrSinNovaJanela('N');
            $objDTO->setStrSinAtivo('S');

            $objDTO = $objBD->cadastrar($objDTO);
        }

        if (!empty($numIdRecurso)) {

            $this->arrMenu[] = array($objDTO->getNumIdItemMenu(), $numIdMenu, $numIdRecurso);
        }

        return $objDTO->getNumIdItemMenu();
    }


    public function addRecursosToPerfil($numIdPerfil, $numIdSistema) {

        if (!empty($this->arrRecurso)) {

            $objDTO = new RelPerfilRecursoDTO();
            $objBD = new RelPerfilRecursoBD(BancoSip::getInstance());

            foreach ($this->arrRecurso as $numIdRecurso) {

                $objDTO->setNumIdSistema($numIdSistema);
                $objDTO->setNumIdPerfil($numIdPerfil);
                $objDTO->setNumIdRecurso($numIdRecurso);

                if ($objBD->contar($objDTO) == 0) {
                    $objBD->cadastrar($objDTO);
                }
            }
        }
    }

    public function addMenusToPerfil($numIdPerfil, $numIdSistema) {

        if (!empty($this->arrMenu)) {

            $objDTO = new RelPerfilItemMenuDTO();
            $objBD = new RelPerfilItemMenuBD(BancoSip::getInstance());

            foreach ($this->arrMenu as $array) {

                list($numIdItemMenu, $numIdMenu, $numIdRecurso) = $array;

                $objDTO->setNumIdPerfil($numIdPerfil);
                $objDTO->setNumIdSistema($numIdSistema);
                $objDTO->setNumIdRecurso($numIdRecurso);
                $objDTO->setNumIdMenu($numIdMenu);
                $objDTO->setNumIdItemMenu($numIdItemMenu);

                if ($objBD->contar($objDTO) == 0) {
                    $objBD->cadastrar($objDTO);
                }
            }
        }
    }

    public function atribuirPerfil($numIdSistema) {
        $objDTO = new PerfilDTO();
        $objBD = new PerfilBD(BancoSip::getInstance());
        $objRN = $this;

        // Vincula a um perfil os recursos e menus adicionados nos m�todos criarMenu e criarReturso
        $fnCadastrar = function($strNome, $numIdSistema) use($objDTO, $objBD, $objRN) {

            $objDTO->unSetTodos();
            $objDTO->setNumIdSistema($numIdSistema);
            $objDTO->setStrNome($strNome, InfraDTO::$OPER_LIKE);
            $objDTO->setNumMaxRegistrosRetorno(1);
            $objDTO->retNumIdPerfil();

            $objPerfilDTO = $objBD->consultar($objDTO);

            if (!empty($objPerfilDTO)) {
                $objRN->addRecursosToPerfil($objPerfilDTO->getNumIdPerfil(), $numIdSistema);
                $objRN->addMenusToPerfil($objPerfilDTO->getNumIdPerfil(), $numIdSistema);
            }
        };

        $fnCadastrar('ADMINISTRADOR', $numIdSistema);
        //$fnCadastrar('BASICO', $numIdSistema);
    }

    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.0
     */
    protected function instalarV100() {
        $numIdSistema = $this->getNumIdSistema('SEI');
        $numIdMenu = $this->getNumIdMenu('Principal', $numIdSistema);

        //----------------------------------------------------------------------
        // Expedir procedimento
        //----------------------------------------------------------------------
        $this->criarRecurso('pen_procedimento_expedir', 'Expedir Procedimento', $numIdSistema);
        $this->criarRecurso('apensados_selecionar_expedir_procedimento', 'Processos Apensados', $numIdSistema);
        $numIdRecurso = $this->criarRecurso('pen_procedimento_expedido_listar', 'Processos Tr�mitados Externamente', $numIdSistema);
        $this->criarMenu('Processos Tr�mitados Externamente', 55, null, $numIdMenu, $numIdRecurso, $numIdSistema);
        //----------------------------------------------------------------------
        // Mapeamento de documentos enviados
        //----------------------------------------------------------------------
        $this->criarRecurso('pen_map_tipo_documento_envio_visualizar', 'Visualiza��o de mapeamento de documentos enviados', $numIdSistema);

        // Acha o menu existente de Tipos de Documento
        $objItemMenuDTO = new ItemMenuDTO();
        $objItemMenuDTO->setNumIdSistema($numIdSistema);
        $objItemMenuDTO->setNumIdMenu($numIdMenu);
        $objItemMenuDTO->setStrRotulo('Tipos de Documento');
        $objItemMenuDTO->setNumMaxRegistrosRetorno(1);
        $objItemMenuDTO->retNumIdItemMenu();

        $objItemMenuBD = new ItemMenuBD(BancoSip::getInstance());
        $objItemMenuDTO = $objItemMenuBD->consultar($objItemMenuDTO);

        if (empty($objItemMenuDTO)) {
            throw new InfraException('Menu "Tipo de Documentos" n�o foi localizado');
        }

        $numIdItemMenuPai = $objItemMenuDTO->getNumIdItemMenu();

        // Gera o submenu Mapeamento
        $_numIdItemMenuPai = $this->criarMenu('Mapeamento', 50, $numIdItemMenuPai, $numIdMenu, null, $numIdSistema);

        // Gera o submenu Mapeamento > Envio
        $numIdItemMenuPai = $this->criarMenu('Envio', 10, $_numIdItemMenuPai, $numIdMenu, null, $numIdSistema);

        // Gera o submenu Mapeamento > Envio > Cadastrar
        $numIdRecurso = $this->criarRecurso('pen_map_tipo_documento_envio_cadastrar', 'Cadastro de mapeamento de documentos enviados', $numIdSistema);
        $this->criarMenu('Cadastrar', 10, $numIdItemMenuPai, $numIdMenu, $numIdRecurso, $numIdSistema);

        // Gera o submenu Mapeamento > Envio > Listar
        $numIdRecurso = $this->criarRecurso('pen_map_tipo_documento_envio_listar', 'Listagem de mapeamento de documentos enviados', $numIdSistema);
        $this->criarMenu('Listar', 20, $numIdItemMenuPai, $numIdMenu, $numIdRecurso, $numIdSistema);

        // Gera o submenu Mapeamento > Recebimento
        $numIdItemMenuPai = $this->criarMenu('Recebimento', 20, $_numIdItemMenuPai, $numIdMenu, null, $numIdSistema);

        // Gera o submenu Mapeamento > Recebimento > Cadastrar
        $numIdRecurso = $this->criarRecurso('pen_map_tipo_documento_recebimento_cadastrar', 'Cadastro de mapeamento de documentos recebidos', $numIdSistema);
        $this->criarMenu('Cadastrar', 10, $numIdItemMenuPai, $numIdMenu, $numIdRecurso, $numIdSistema);

        // Gera o submenu Mapeamento > Recebimento > Listar
        $numIdRecurso = $this->criarRecurso('pen_map_tipo_documento_recebimento_listar', 'Listagem de mapeamento de documentos recebidos', $numIdSistema);
        $this->criarMenu('Listar', 20, $numIdItemMenuPai, $numIdMenu, $numIdRecurso, $numIdSistema);

        //Atribui as permiss�es aos recursos e menus
        $this->atribuirPerfil($numIdSistema);

        // ---------- antigo m�todo (instalarV003R003S003IW001) ---------- //
        $objBD = new ItemMenuBD(BancoSip::getInstance());

        // Achar o root
        $numIdSistema = $this->getNumIdSistema('SEI');
        $numIdMenu = $this->getNumIdMenu('Principal', $numIdSistema);

        $objDTO = new ItemMenuDTO();
        $objDTO->setNumIdSistema($numIdSistema);
        $objDTO->setNumIdMenu($numIdMenu);
        $objDTO->setStrRotulo('Administra��o');
        $objDTO->setNumMaxRegistrosRetorno(1);
        $objDTO->retNumIdItemMenu();

        $objDTO = $objBD->consultar($objDTO);

        if (empty($objDTO)) {
            throw new InfraException('Menu "Administra��o" n�o foi localizado');
        }

        $numIdItemMenuRoot = $objDTO->getNumIdItemMenu();
        //----------------------------------------------------------------------
        // Acha o nodo do mapeamento

        $objItemMenuDTO = new ItemMenuDTO();
        $objItemMenuDTO->setNumIdSistema($numIdSistema);
        $objItemMenuDTO->setNumIdMenu($numIdMenu);
        $objItemMenuDTO->setStrRotulo('Mapeamento');
        $objItemMenuDTO->setNumSequencia(50);
        $objItemMenuDTO->setNumMaxRegistrosRetorno(1);
        $objItemMenuDTO->retTodos();

        $objItemMenuDTO = $objBD->consultar($objItemMenuDTO);
        if (!empty($objItemMenuDTO)) {

            $numIdItemMenuMapeamento = $objItemMenuDTO->getNumIdItemMenu();

            $objDTO = new ItemMenuDTO();
            $objDTO->setNumIdSistema($numIdSistema);
            $objDTO->setNumIdMenu($numIdMenu);
            $objDTO->setNumIdItemMenuPai($numIdItemMenuMapeamento);
            $objDTO->retTodos();

            $arrObjDTO = $objBD->listar($objDTO);

            if (!empty($arrObjDTO)) {

                $numIdItemMenuPai = $this->criarMenu('Processo Eletr�nico Nacional', 0, $numIdItemMenuRoot, $numIdMenu, null, $numIdSistema);
                $numIdItemMenuPai = $this->criarMenu('Mapeamento de Tipos de Documento', 10, $numIdItemMenuPai, $numIdMenu, null, $numIdSistema);

                foreach ($arrObjDTO as $objDTO) {

                    $objDTO->setNumIdItemMenuPai($numIdItemMenuPai);

                    $objBD->alterar($objDTO);
                }

                $objBD->excluir($objItemMenuDTO);
            }
        }

        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO_ANTIGO);
        $objInfraParametroDTO->setStrValor('1.0.0');

        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroBD->cadastrar($objInfraParametroDTO);
    }

    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.0.1
     */
    protected function instalarV101() {
        // ---------- antigo m�todo (instalarV006R004S001US039) ---------- //
        $objItemMenuBD = new ItemMenuBD($this->inicializarObjInfraIBanco());

        $numIdSistema = $this->getNumIdSistema('SEI');
        $numIdMenu = $this->getNumIdMenu('Principal', $numIdSistema);

        $objItemMenuDTO = new ItemMenuDTO();
        $objItemMenuDTO->setNumIdSistema($numIdSistema);
        $objItemMenuDTO->setNumIdMenu($numIdMenu);
        $objItemMenuDTO->setStrRotulo('Processo Eletr�nico Nacional');
        $objItemMenuDTO->setNumMaxRegistrosRetorno(1);
        $objItemMenuDTO->retNumIdItemMenu();

        $objItemMenuDTO = $objItemMenuBD->consultar($objItemMenuDTO);

        if(empty($objItemMenuDTO)) {
            throw new InfraException('Menu "Processo Eletr�nico Nacional" n�o foi localizado');
        }

        // Administrao > Mapeamento de Hip�teses Legais de Envio
        $numIdItemMenu = $this->criarMenu('Mapeamento de Hip�teses Legais', 20, $objItemMenuDTO->getNumIdItemMenu(), $numIdMenu, null, $numIdSistema);

        // Administrao > Mapeamento de Hip�teses Legais de Envio > Envio
        $numIdItemMenu = $this->criarMenu('Envio', 10, $numIdItemMenu, $numIdMenu, null, $numIdSistema);

        // Administrao > Mapeamento de Hip�teses Legais de Envio > Envio > Cadastrar
        $numIdRecurso = $this->criarRecurso('pen_map_hipotese_legal_enviado_alterar', 'Alterar de mapeamento de Hip�teses Legais de Envio', $numIdSistema);
        $numIdRecurso = $this->criarRecurso('pen_map_hipotese_legal_enviado_cadastrar', 'Cadastro de mapeamento de Hip�teses Legais de Envio', $numIdSistema);
        $this->criarMenu('Cadastrar', 10, $numIdItemMenu, $numIdMenu, $numIdRecurso, $numIdSistema);

        // Administrao > Mapeamento de Hip�teses Legais de Envio > Envio > Listar
        $numIdRecurso = $this->criarRecurso('pen_map_hipotese_legal_enviado_excluir', 'Excluir mapeamento de Hip�teses Legais de Envio', $numIdSistema);
        $numIdRecurso = $this->criarRecurso('pen_map_hipotese_legal_enviado_listar', 'Listagem de mapeamento de Hip�teses Legais de Envio', $numIdSistema);
        $this->criarMenu('Listar', 20, $numIdItemMenu, $numIdMenu, $numIdRecurso, $numIdSistema);

        //Atribui as permisses aos recursos e menus
        $this->atribuirPerfil($numIdSistema);


        // ---------- antigo m�todo (instalarV006R004S001US040) ---------- //
        $objBD = new ItemMenuBD($this->inicializarObjInfraIBanco());

        //----------------------------------------------------------------------
        // Achar o root

        $numIdSistema = $this->getNumIdSistema('SEI');
        $numIdMenu = $this->getNumIdMenu('Principal', $numIdSistema);

        $objDTO = new ItemMenuDTO();
        $objDTO->setNumIdSistema($numIdSistema);
        $objDTO->setNumIdMenu($numIdMenu);
        $objDTO->setStrRotulo('Mapeamento de Hip�teses Legais');
        $objDTO->setNumMaxRegistrosRetorno(1);
        $objDTO->retNumIdItemMenu();

        $objDTO = $objBD->consultar($objDTO);

        if(empty($objDTO)) {
            throw new InfraException('Menu "Processo Eletr�nico Nacional" n�o foi localizado');
        }

        // Administrao > Mapeamento de Hip�teses Legais de Envio > Envio
        $numIdItemMenu = $this->criarMenu('Recebimento', 20, $objDTO->getNumIdItemMenu(), $numIdMenu, null, $numIdSistema);

        // Administrao > Mapeamento de Hip�teses Legais de Envio > Envio > Cadastrar
        $numIdRecurso = $this->criarRecurso('pen_map_hipotese_legal_recebido_alterar', 'Altera��o de mapeamento de Hip�teses Legais de Recebimento', $numIdSistema);
        $numIdRecurso = $this->criarRecurso('pen_map_hipotese_legal_recebido_cadastrar', 'Cadastro de mapeamento de Hip�teses Legais de Recebimento', $numIdSistema);
        $this->criarMenu('Cadastrar', 10, $numIdItemMenu, $numIdMenu, $numIdRecurso, $numIdSistema);

        // Administrao > Mapeamento de Hip�teses Legais de Envio > Envio > Listar
        $numIdRecurso = $this->criarRecurso('pen_map_hipotese_legal_recebido_excluir', 'Exclus�o de mapeamento de Hip�teses Legais de Recebimento', $numIdSistema);
        $numIdRecurso = $this->criarRecurso('pen_map_hipotese_legal_recebido_listar', 'Listagem de mapeamento de Hip�teses Legais de Recebimento', $numIdSistema);
        $this->criarMenu('Listar', 20, $numIdItemMenu, $numIdMenu, $numIdRecurso, $numIdSistema);

        //Atribui as permisses aos recursos e menus
        $this->atribuirPerfil($numIdSistema);

        // ---------- antigo m�todo (instalarV006R004S001US043) ---------- //
        $objBD = new ItemMenuBD($this->inicializarObjInfraIBanco());

        $numIdSistema = $this->getNumIdSistema('SEI');
        $numIdMenu = $this->getNumIdMenu('Principal', $numIdSistema);

        $objDTO = new ItemMenuDTO();
        $objDTO->setNumIdSistema($numIdSistema);
        $objDTO->setNumIdMenu($numIdMenu);
        $objDTO->setStrRotulo('Mapeamento de Hip�teses Legais');
        $objDTO->setNumMaxRegistrosRetorno(1);
        $objDTO->retNumIdItemMenu();

        $objDTO = $objBD->consultar($objDTO);

        if(empty($objDTO)) {
            throw new InfraException('Menu "Processo Eletr�nico Nacional" n�o foi localizado');
        }

        $numIdRecurso = $this->criarRecurso('pen_map_hipotese_legal_padrao_cadastrar', 'Acesso ao formul�rio de cadastro de mapeamento de Hip�teses Legais Padr�o', $numIdSistema);

        $this->criarMenu('Hip�tese de Restri��o Padr�o', 30, $objDTO->getNumIdItemMenu(), $numIdMenu, $numIdRecurso, $numIdSistema);
        $this->criarRecurso('pen_map_hipotese_legal_padrao', 'M�todo Cadastrar Padr�o da RN de mapeamento de Hip�teses Legais', $numIdSistema);
        $this->atribuirPerfil($numIdSistema);

        /* altera o par�metro da vers�o de banco */
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO_ANTIGO);
        $objInfraParametroDTO->retTodos();

        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.0.1');
        $objInfraParametroBD->alterar($objInfraParametroDTO);

    }

    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.1.0
     */
    protected function instalarV102() {

        $objBD = new ItemMenuBD($this->inicializarObjInfraIBanco());

        //----------------------------------------------------------------------
        // Achar o sistema
        $numIdSistema = $this->getNumIdSistema('SEI');
        $numIdMenu = $this->getNumIdMenu('Principal', $numIdSistema);

        $objDTO = new ItemMenuDTO();
        $objDTO->setNumIdSistema($numIdSistema);
        $objDTO->setNumIdMenu($numIdMenu);
        $objDTO->setStrRotulo('Processo Eletr�nico Nacional');
        $objDTO->setNumMaxRegistrosRetorno(1);
        $objDTO->retNumIdItemMenu();

        $objDTO = $objBD->consultar($objDTO);

        if(empty($objDTO)) {
            throw new InfraException('Menu "Processo Eletr�nico Nacional" n�o foi localizado');
        }

        // Administrao > Mapeamento de Hip�teses Legais de Envio > Envio
        $numIdItemMenu = $this->criarMenu('Mapeamento de Unidades', 20, $objDTO->getNumIdItemMenu(), $numIdMenu, null, $numIdSistema);

        // Cadastro do menu de administra��o par�metros
        $numIdRecurso = $this->criarRecurso('pen_parametros_configuracao', 'Parametros de Configura��o', $numIdSistema);
        $this->criarMenu('Par�metros de Configura��o', 20, $objDTO->getNumIdItemMenu(), $numIdMenu, $numIdRecurso, $numIdSistema);

        // Administrao > Mapeamento de Hip�teses Legais de Envio > Envio > Cadastrar
        $this->criarRecurso('pen_map_unidade_alterar', 'Altera��o de mapeamento de Unidades', $numIdSistema);
        $numIdRecurso = $this->criarRecurso('pen_map_unidade_cadastrar', 'Cadastro de mapeamento de Unidades', $numIdSistema);
        $this->criarMenu('Cadastrar', 10, $numIdItemMenu, $numIdMenu, $numIdRecurso, $numIdSistema);

        // Administrao > Mapeamento de Hip�teses Legais de Envio > Envio > Listar
        $this->criarRecurso('pen_map_unidade_excluir', 'Exclus�o de mapeamento de Unidades', $numIdSistema);
        $numIdRecurso = $this->criarRecurso('pen_map_unidade_listar', 'Listagem de mapeamento de Unidades', $numIdSistema);
        $this->criarMenu('Listar', 20, $numIdItemMenu, $numIdMenu, $numIdRecurso, $numIdSistema);


        // ------------------ Atribui as permisses aos recursos e menus ----------------------//
        $this->atribuirPerfil($numIdSistema);

        /* altera o par�metro da vers�o de banco */
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO_ANTIGO);
        $objInfraParametroDTO->retTodos();

        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.1.0');
        $objInfraParametroBD->alterar($objInfraParametroDTO);

    }

    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.0.3
     */
    protected function instalarV103() {
        $numIdSistema = $this->getNumIdSistema('SEI');

        //Alterar rotulo do menu
        $objDTO = new ItemMenuDTO();
        $objDTO->setStrRotulo('Indicar Hiptese de Restrio Padro');
        $objDTO->retNumIdItemMenu();
        $objDTO->retNumIdMenu();
        $objBD = new ItemMenuBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrRotulo('Hip�tese de Restri��o Padr�o');
            $objBD->alterar($objDTO);
        }

        //Alterar nomeclatura do recurso
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_hipotese_legal_recebido_listar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_hipotese_legal_recebimento_listar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_hipotese_legal_recebimento_listar');
            $objBD->alterar($objDTO);
        }

        //Alterar nomeclatura do recurso
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_hipotese_legal_recebido_excluir');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_hipotese_legal_recebimento_excluir');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_hipotese_legal_recebimento_excluir');
            $objBD->alterar($objDTO);
        }

        //Alterar nomeclatura do recurso
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_hipotese_legal_recebido_cadastrar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_hipotese_legal_recebimento_cadastrar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_hipotese_legal_recebimento_cadastrar');
            $objBD->alterar($objDTO);
        }

        //Alterar nomeclatura do recurso
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_hipotese_legal_recebido_alterar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_hipotese_legal_recebimento_alterar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_hipotese_legal_recebimento_alterar');
            $objBD->alterar($objDTO);
        }

        //Alterar nomeclatura do recurso
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_hipotese_legal_enviado_listar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_hipotese_legal_envio_listar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_hipotese_legal_envio_listar');
            $objBD->alterar($objDTO);
        }

        //Alterar nomeclatura do recurso
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_hipotese_legal_enviado_excluir');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_hipotese_legal_envio_excluir');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_hipotese_legal_envio_excluir');
            $objBD->alterar($objDTO);
        }

        //Alterar nomeclatura do recurso
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_hipotese_legal_enviado_cadastrar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_hipotese_legal_envio_cadastrar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_hipotese_legal_envio_cadastrar');
            $objBD->alterar($objDTO);
        }

        //Alterar nomeclatura do recurso
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_hipotese_legal_enviado_alterar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_hipotese_legal_envio_alterar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_hipotese_legal_envio_alterar');
            $objBD->alterar($objDTO);
        }

        //Cadastrar recurso de altera��o dos par�metros
        $this->criarRecurso('pen_parametros_configuracao_alterar', 'Altera��o de parametros de configura��o do m�dulo PEN', $numIdSistema);

        /* altera o par�metro da vers�o de banco */
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO_ANTIGO);
        $objInfraParametroDTO->retTodos();
        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.0.3');
        $objInfraParametroBD->alterar($objInfraParametroDTO);
    }

    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.0.4
     */
    protected function instalarV104() {
        $numIdSistema = $this->getNumIdSistema('SEI');

        //Cadastrar recurso Mapeamento dos Tipo de documentos enviados
        $this->criarRecurso('pen_map_tipo_documento_envio_alterar', 'Altera��o de mapeamento de documentos enviados', $numIdSistema);
        $this->criarRecurso('pen_map_tipo_documento_envio_excluir', 'Exclus�o de mapeamento de documentos enviados', $numIdSistema);

        //Cadastrar recurso Mapeamento dos Tipo de documentos recebido
        $this->criarRecurso('pen_map_tipo_documento_recebimento_alterar', 'Altera��o de mapeamento de documentos recebimento', $numIdSistema);
        $this->criarRecurso('pen_map_tipo_documento_recebimento_excluir', 'Exclus�o de mapeamento de documentos recebimento', $numIdSistema);
        $this->criarRecurso('pen_map_tipo_documento_recebimento_visualizar', 'Visualiza��o de mapeamento de documentos recebimento', $numIdSistema);

        //Alterar nomeclatura do recurso (recebido)
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_tipo_doc_recebido_cadastrar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_tipo_documento_recebimento_cadastrar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_tipo_documento_recebimento_cadastrar');
            $objBD->alterar($objDTO);
        }
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_tipo_doc_enviado_visualizar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_tipo_documento_envio_visualizar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_tipo_documento_envio_visualizar');
            $objBD->alterar($objDTO);
        }
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_tipo_doc_recebido_listar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_tipo_documento_recebimento_listar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_tipo_documento_recebimento_listar');
            $objBD->alterar($objDTO);
        }

        //Alterar nomeclatura do recurso (envio)
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_tipo_doc_enviado_cadastrar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_tipo_documento_envio_cadastrar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_tipo_documento_envio_cadastrar');
            $objBD->alterar($objDTO);
        }
        $objDTO = new RecursoDTO();
        $objDTO->setStrNome('pen_map_tipo_doc_enviado_listar');
        $objDTO->retNumIdRecurso();
        $objBD = new RecursoBD($this->getObjInfraIBanco());
        $objDTO = $objBD->consultar($objDTO);
        if ($objDTO) {
            $objDTO->setStrNome('pen_map_tipo_documento_envio_listar');
            $objDTO->setStrCaminho('controlador.php?acao=pen_map_tipo_documento_envio_listar');
            $objBD->alterar($objDTO);
        }

        /* altera o par�metro da vers�o de banco */
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO_ANTIGO);
        $objInfraParametroDTO->retTodos();
        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.0.4');
        $objInfraParametroBD->alterar($objInfraParametroDTO);
    }

    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.1.1
     */
    protected function instalarV111() {
        $numIdSistema = $this->getNumIdSistema('SEI');

        //Ajuste em nome da vari�vel de vers�o do m�dulo VERSAO_MODULO_PEN
        BancoSIP::getInstance()->executarSql("update infra_parametro set nome = '" . self::PARAMETRO_VERSAO_MODULO . "' where nome = '" . self::PARAMETRO_VERSAO_MODULO_ANTIGO . "'");

        //Adequa��o em nome de recursos do m�dulo
        $this->renomearRecurso($numIdSistema, 'apensados_selecionar_expedir_procedimento', 'pen_apensados_selecionar_expedir_procedimento');

        //Atualiza��o com recursos n�o adicionados automaticamente em vers�es anteriores
        $this->arrRecurso = array_merge($this->arrRecurso, array(
            $this->consultarRecurso($numIdSistema, "pen_map_tipo_documento_envio_alterar"),
            $this->consultarRecurso($numIdSistema, "pen_map_tipo_documento_envio_excluir"),
            $this->consultarRecurso($numIdSistema, "pen_map_tipo_documento_recebimento_alterar"),
            $this->consultarRecurso($numIdSistema, "pen_map_tipo_documento_recebimento_excluir"),
            $this->consultarRecurso($numIdSistema, "pen_map_tipo_documento_recebimento_visualizar"),
            $this->consultarRecurso($numIdSistema, "pen_parametros_configuracao_alterar")
            ));

        $this->atribuirPerfil($numIdSistema);

        $objPerfilRN = new PerfilRN();
        $objPerfilDTO = new PerfilDTO();
        $objPerfilDTO->retNumIdPerfil();
        $objPerfilDTO->setNumIdSistema($numIdSistema);
        $objPerfilDTO->setStrNome('Administrador');
        $objPerfilDTO = $objPerfilRN->consultar($objPerfilDTO);
        if ($objPerfilDTO == null){
            throw new InfraException('Perfil Administrador do sistema SEI n�o encontrado.');
        }

        $numIdPerfilSeiAdministrador = $objPerfilDTO->getNumIdPerfil();

        $objRelPerfilRecursoDTO = new RelPerfilRecursoDTO();
        $objRelPerfilRecursoDTO->retTodos();
        $objRelPerfilRecursoDTO->setNumIdSistema($numIdSistema);
        $objRelPerfilRecursoDTO->setNumIdPerfil($numIdPerfilSeiAdministrador);
        $arrRecursosRemoverAdministrador = array(
            $this->consultarRecurso($numIdSistema, "pen_procedimento_expedido_listar"),
            $this->consultarRecurso($numIdSistema, "pen_procedimento_expedir"),
            );
        $objRelPerfilRecursoDTO->setNumIdRecurso($arrRecursosRemoverAdministrador, InfraDTO::$OPER_IN);
        $objRelPerfilRecursoRN = new RelPerfilRecursoRN();
        $objRelPerfilRecursoRN->excluir($objRelPerfilRecursoRN->listar($objRelPerfilRecursoDTO));

        /* Corrigir a vers�o do m�dulo no banco de dados */
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO);
        $objInfraParametroDTO->retTodos();
        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.1.1');
        $objInfraParametroBD->alterar($objInfraParametroDTO);
    }


    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.1.9
     */
    protected function instalarV119()
    {
        /* Corrige nome de menu de tr�mite de documentos */
        $numIdSistema = $this->getNumIdSistema('SEI');
        $numIdMenuPai = $this->getNumIdMenu('Principal', $numIdSistema);

        //Corrige nome do recurso
        $objRecursoDTO = new RecursoDTO();
        $objRecursoDTO->setNumIdSistema($numIdSistema);
        $objRecursoDTO->setStrNome('pen_procedimento_expedido_listar');
        $objRecursoDTO->retNumIdRecurso();
        $objRecursoBD = new RecursoBD($this->getObjInfraIBanco());
        $objRecursoDTO = $objRecursoBD->consultar($objRecursoDTO);
        if(isset($objRecursoDTO)){
            $numIdRecurso = $objRecursoDTO->getNumIdRecurso();
            $objRecursoDTO->setStrDescricao('Processos Tramitados Externamente');
            $objRecursoBD->alterar($objRecursoDTO);
        }

        $objItemMenuDTO = new ItemMenuDTO();
        $objItemMenuDTO->setNumIdItemMenuPai(null);
        $objItemMenuDTO->setNumIdSistema($numIdSistema);
        $objItemMenuDTO->setNumIdRecurso($numIdRecurso);
        $objItemMenuDTO->setStrRotulo('Processos Tr�mitados Externamente');
        $objItemMenuDTO->retNumIdMenu();
        $objItemMenuDTO->retNumIdItemMenu();
        $objItemMenuBD = new ItemMenuBD(BancoSip::getInstance());
        $objItemMenuDTO = $objItemMenuBD->consultar($objItemMenuDTO);
        if(isset($objItemMenuDTO)){
            $objItemMenuDTO->setStrDescricao('Processos Tramitados Externamente');
            $objItemMenuDTO->setStrRotulo('Processos Tramitados Externamente');
            $objItemMenuBD->alterar($objItemMenuDTO);
        }

        //Corrigir a vers�o do m�dulo no banco de dados
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO);
        $objInfraParametroDTO->retTodos();
        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.1.9');
        $objInfraParametroBD->alterar($objInfraParametroDTO);
    }

    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.1.10
     */
    protected function instalarV1110()
    {
         //Corrigir a vers�o do m�dulo no banco de dados
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO);
        $objInfraParametroDTO->retTodos();
        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.1.10');
        $objInfraParametroBD->alterar($objInfraParametroDTO);
    }

    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.1.11
     */
    protected function instalarV1111()
    {
         //Corrigir a vers�o do m�dulo no banco de dados
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO);
        $objInfraParametroDTO->retTodos();
        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.1.11');
        $objInfraParametroBD->alterar($objInfraParametroDTO);
    }

    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.1.12
     */
    protected function instalarV1112()
    {
         //Corrigir a vers�o do m�dulo no banco de dados
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO);
        $objInfraParametroDTO->retTodos();
        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.1.12');
        $objInfraParametroBD->alterar($objInfraParametroDTO);
    }


    /**
     * Instala/Atualiza os m�dulo PEN para vers�o 1.1.13
     */
    protected function instalarV1113()
    {
         //Corrigir a vers�o do m�dulo no banco de dados
        $objInfraParametroDTO = new InfraParametroDTO();
        $objInfraParametroDTO->setStrNome(self::PARAMETRO_VERSAO_MODULO);
        $objInfraParametroDTO->retTodos();
        $objInfraParametroBD = new InfraParametroBD($this->inicializarObjInfraIBanco());
        $objInfraParametroDTO = $objInfraParametroBD->consultar($objInfraParametroDTO);
        $objInfraParametroDTO->setStrValor('1.1.13');
        $objInfraParametroBD->alterar($objInfraParametroDTO);
    }

}

try {
    $objAtualizarRN = new PenAtualizarSipRN($arrArgs);
    $objAtualizarRN->atualizarVersao();
    exit(0);
} catch (Exception $e) {
    print InfraException::inspecionar($e);
    exit(1);
}

print PHP_EOL;
