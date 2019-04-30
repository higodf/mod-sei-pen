#!/bin/bash

PATH=/usr/bin:/sbin:/bin:/usr/sbin
export PATH

echo "Iniciando execução dos script";
GEARMAN=$(ls /etc/init.d | grep -owih gearman.*)
if [[ -z $GEARMAN ]]; then
	echo "ERROR: Instalação do Gearman não pode ser localizada." 	
	exit 1
else
	ps cax | grep -ih gearman.* > /dev/null
	if [ $? -ne 0 ]; then
        echo "Gearman: Iniciando serviço de gerenciamento de fila de tarefas..."
		/etc/init.d/$GEARMAN start;	        
	fi
fi

echo "Verificando supervisor...";
SUPERVISOR=$(ls /etc/init.d | grep -owih supervisor.*)
if [[ -z $SUPERVISOR ]]; then
	echo "ERROR: Instalação do Supervisor não pode ser localizada."
	exit 1
else
	ps cax | grep -ih supervisor.* > /dev/null
	if [ $? -ne 0 ]; then
            echo "Supervisor: Iniciando serviço de monitoramento dos processos de integração..."
        	/etc/init.d/$SUPERVISOR start;
	else
	
	    COMMAND=$(ps -C php -f | grep -o "PendenciasTramiteRN.php");
	    if [ -z "$COMMAND" ]; then
            echo "Supervisor: Reiniciando serviço de monitoramento dos processos de integração..."
            /etc/init.d/$SUPERVISOR stop;
            /etc/init.d/$SUPERVISOR start;                 
	    fi
	
        COMMAND=$(ps -C php -f | grep -o "ProcessarPendenciasRN.php");
        if [ -z "$COMMAND" ]; then
            echo "Supervisor: Reiniciando serviço de monitoramento dos processos de integração..."
	        /etc/init.d/$SUPERVISOR stop;
	        /etc/init.d/$SUPERVISOR start;	                
	    fi
	fi
fi

echo "Verificando gearman...";
# Garante que comando grep está disponível
if ! [ -x "$(command -v grep)" ]; then
    echo "Error: comando grep não encontrado" >&2
    exit 1
fi

# Garante que comando gearadmin está disponível
if ! [ -x "$(command -v gearadmin)" ]; then
    echo "Error: comando geardmin não encontrado" >&2
    exit 1
fi

#Verifica se existe alguma fila de processamento parada por causa de um worker bloqueado
#Este caso é identificado quando o retorno do gearadmin --status encontra a seguinte situação:
# gearadmin --status
#   receberProcedimento     5       0       1
#                           |       |       |
#                           |       |       |
# jobs na fila -------------        |       |
# jobs em processamento ------------        |
# workers conectados ----------------------- 
# 
# Quando existe jobs disponíveis para processamento, workers conectados e nunhum trabalho em processamento; 
# isto indica que houve algum bloqueio no Worker que está impedindo-o a processar os trabalhos da fila.
# Nesta situação, processos do german precisam ser reiniciados.

GEARADMIN_STATUS=$(gearadmin --status)
GEARADMIN_REGEX="([1-9]+|[1-9]+[0-9]*)+[[:space:]]+0+[[:space:]]+([1-9]+|[1-9]+[0-9]*)+"

if echo "$GEARADMIN_STATUS" | grep -Eq "$GEARADMIN_REGEX"; then
    echo "Supervisor: Reiniciando serviço de monitoramento dos processos de integração..."
    /etc/init.d/$SUPERVISOR stop;
    /etc/init.d/$SUPERVISOR start; 
fi
