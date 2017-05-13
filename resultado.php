<?php 
	$gramatica = str_split($_POST['gramatica']);  //Pega a producao digitada
	$terminais = explode(',',$_POST['terminais']);  //Pega a producao digitada
	
	$simbolos = criarSimbolos($gramatica);	
	$naoterminais = getNT($simbolos);

	$simbolos = verificarAmbiguidade($simbolos, $terminais, $naoterminais);	
	$simbolos = verificarRecursao($simbolos);
	
	$first = getFirst($simbolos, $terminais, $naoterminais);

	$follow = getFollow($simbolos, $terminais, $first);
	$tabela = geraTabela($first, $terminais, $naoterminais, $follow, $simbolos);

	$entrada = $_POST['entrada'];
	reconheceEntrada($tabela,$entrada,$terminais,$naoterminais[0]);
?>

<?php 
	function criarSimbolos($gramatica){
		$ladoDireito = ""; //Cria o lado direito
		$ladoEsquerdo = ""; //Cria o lado esquerdo
		$lado = 0; //Lado a ser lido
		$simbolos = []; //Array com simbolos

		foreach($gramatica as $indice => $simbolo){
			if($lado == 0 && !preg_match('/[-,>, ]/', $simbolo)){ //Armazena lado esquedo
				$ladoEsquerdo .= $simbolo; 
			}
			
			if($lado == 1){ //Se a leitura passar do simbolo ' -> '
				if(preg_match('/[|]/', $simbolo) || $simbolo == "\n" || strlen($_POST['gramatica']) == ($indice+1)){ //Testa se encontro o fim, \n ou |
					if(strlen($_POST['gramatica']) == ($indice+1)){ //Testa se for o ultimo elemento da string toda
						$simbolos[$ladoEsquerdo][] = $ladoDireito.$simbolo;	//Armazena o simbolo do lado direito que já tinha + a letra atual
						continue; //Termina a criação de simbolos
					}		
					$simbolos[$ladoEsquerdo][] = $ladoDireito;//Armazena o simbolo do lado direito
					$ladoDireito = "";	//Limpa a variavel pra armazenar um novo elemento
					if($simbolo == "\n"){ //Se terminou os simbolos do lado direito terminou
						$ladoEsquerdo = ""; //Limpa a var do lado esquerdo
						$lado = 0; //Começa a armazenar a proxima letra
					}	
				}
				elseif(!preg_match('/\s/', $simbolo)){
					$ladoDireito .= $simbolo ; //Armazena a letra em simbolo
				}
			}
			
			if($lado == 0 && preg_match('/>/', $simbolo)){ //Se chegou no fim do caracter ' -> '
				$lado = 1 ; //Coloca 1 para indicar que ira começar a armazenar o lado direito
			}			
		}
		return $simbolos;
	}
	
	function verificarRecursao($simbolos){
		$recursao = false;
		$novoSimbolo = [];
		
		foreach($simbolos as $ladoEsquerdo => $ladoDireito){ //Verifica lado direito de cada simbolo do lado esquerdo
			foreach($ladoDireito as $simbolo){ //Verifica todo o lado direito
				if($ladoEsquerdo == $simbolo[0]){ //Verifica se tem recursao 
					$recursao = true;
					break;
				}
			}			
			if($recursao){ //Se tiver recursao
				foreach($ladoDireito as $indice => $simbolo){ //Procura simbolo do lado esquerdo no lado direito
					if(preg_match('/'.$ladoEsquerdo.'/',$simbolo)){ //Se achou, Cria novo simbolo do lado esquerdo e direito
						$novoSimbolo[$ladoEsquerdo.'\''][] = str_replace($ladoEsquerdo,'',$simbolo).$ladoEsquerdo.'\'';
						unset($simbolos[$ladoEsquerdo][$indice]); //Remove lado direito do lado esquerdo atual
					}
					else{ //Senão adiciona o novo simbolo no final do lado direito
						$simbolos[$ladoEsquerdo][$indice] = $simbolo.$ladoEsquerdo.'\'';
					}
				}				
				$novoSimbolo[$ladoEsquerdo.'\''][] = 'X'; //Adiciona simbolo vazio
				$recursao = false; //Coloca em falso para o proximo simbolo do lado esquerdo
			}
		}
		return array_merge($simbolos,$novoSimbolo);
	}
	
	function organizarArraySimbolos($a,$b){ 
		return strlen($b)-strlen($a); //Função para ordernar do maior terminal pro menor   Ex: aaa / aa / a
	}
	
	function unirSimbolos($terminais,$naoTerminais){
		$simbolos = [];
		usort($terminais,'organizarArraySimbolos'); //Organiza os simbolos terminais do maior para o menor
		usort($naoTerminais,'organizarArraySimbolos'); //Organiza os simbolos não-terminais do maior para o menor
		foreach($terminais as $t){ //Uni os simbolos
			$simbolos[] = $t;
		}
		foreach($naoTerminais as $nt){ //Uni os simbolos
			$simbolos[] = $nt;
		}
		return $simbolos;
	}
	
	function verificarAmbiguidade($simbolos,$terminais,$naoTerminais){
		$ambigua = false;
		$novoSimbolo = [];
		$simboloSeparado = [];
		$novaLetra = '';	
		
		foreach($simbolos as $ladoEsquerdo => $ladoDireito){ 
			foreach($ladoDireito as $indice => $simbolo){
				for($i = $indice+1; $i < count($ladoDireito); $i++){					
					if(strlen($simbolo) == strlen($ladoDireito[$i])){ //Se tamanho do simbolos forem iguais						
						$ambigua = verificarSimbolos(unirSimbolos($terminais,$naoTerminais),$ladoDireito[$i],$simbolo); //Testa se há ambiguidade
						if($ambigua){ //Se houver
							$novaLetra = gerarSimboloNovo($naoTerminais); //Gera uma letra nova
							$naoTerminais[] = $novaLetra; //Adiciona essa letra nova em um array para reconhecer os não-terminais
							$novoSimbolo[$novaLetra] = [str_replace($ladoEsquerdo,$novaLetra,$ladoDireito[$i]),$ladoEsquerdo]; //Arruma a ambiguidade
							unset($simbolos[$ladoEsquerdo][$i]); //
						}
					}
				}
			}
		}
		return array_merge($simbolos,$novoSimbolo);
	}

	function verificarSimbolos($simbolos,$ladoDireito,$simboloLido){
		foreach($simbolos as $simbolo){ //Compara as sentenças para achar a ambiguidade, se tiverem tamanho ou simbolos diferentes retorna falso pra ambiguidade
			if((strlen($simboloLido) != strlen($ladoDireito)) || (preg_match('/'.$simbolo.'/',$simboloLido) != preg_match('/'.$simbolo.'/',$ladoDireito))){
				return false;			
			}
			$simboloLido = preg_replace('/'.$simbolo.'/','',$simboloLido); //Se passarem nos teste anterior é reduzido os simbolos e feito a comparação
			$ladoDireito = preg_replace('/'.$simbolo.'/','',$ladoDireito); //até que as duas sentenças estejam vazias
			if(empty($simboloLido) && empty($ladoDireito)){ //Se estiverem vazias, sai do comparação e retorna verdadeiro pra ambiguidade
				break;
			}
		}
		return true;
	}
	
	function gerarSimboloNovo($naoTerminais){ 
		$novoSimbolo = false; 
		while($novoSimbolo == false){ //Se foi encontrado um simbolo novo retorna o simbolo
			$simboloGerado = chr(rand(65,90)); //Gera uma letra nova que sera usada para tratar a ambiguidade
			if(!in_array($simboloGerado,$naoTerminais) && $simboloGerado != 'X'){ //Se o simbolo não estiver no array de não-terminais e não for o simbolo
				$novoSimbolo = true; 											  //de vazio sai da procura
			}
		}
		return $simboloGerado;
	}
	
	function getFirst($simbolos, $terminais, $naoterminais){
		array_push($terminais, 'X');
		$first = array();
            foreach ($simbolos as $key => $s) {
				foreach($s as $x){
					foreach($terminais as $k => $t){
						if(strpos($x, $t) === 0){
							$first[$key][] = $t;
							$first[$key][$t][] = $x;
						} else{
							foreach($naoterminais as $k => $n){
								if(strpos($x, $n) === 0){ // compara primeiro caracter da produção do lado direito com não terminal para verificar se é um nao terminal
									foreach($simbolos[$n] as $sim){
										if(strpos($sim, $t) === 0){ // compara primeiro caractere da produção para verificar se é um terminal
											$first[$key][] = $t;
											$first[$key][$t][] = $x;
										} else{
											$first = getTerminalByNT($naoterminais, $sim, $x, $t, $key, $first, $simbolos, $terminais);
										}
									}
								}
							}
							
						}
					
					$pos = strpos($x, $t);
					
					}
				}
            }
			return $first;
	}
	
	function isTerminal($terminais, $variavel){
		if (in_array($variavel, $terminais)) { 
			return true;
		} else{
			foreach($terminais as $t){
			if(strpos($variavel, $t) === 0){ // compara o primeiro caractere da produção para verificar se é um terminal
					return true;
				}
			}
		}
		return false;
	}

	function getPrimeiroNT($sim, $naoterminais){ // pega o primeiro nao terminal
		if (in_array($sim[0], $naoterminais)) {
			return $sim[0];
		}
		return false;
	}
	
	function getTerminalByNT($naoterminais, $sim, $x, $t, $key, $first, $simbolos, $terminais){
		$nt = getPrimeiroNT($sim, $naoterminais); // pega o primeiro nao terminal
		if($nt!=false){
			foreach($simbolos[$nt] as $k => $s){
				if(isTerminal($terminais, $s)){ // verifica se o simbolo é terminal
					foreach($terminais as $t){ // se for terminal verifica se é apenas 1 carectere ou mais (Ex: id) -- n ta funfando ele pega todos 
						if(strpos($s, $t) === 0){ // compara primeiro caractere da produção para verificar se é um terminal
								$first[$key][] = $s[0];
								$first[$key][$s[0]][] = $x;
						}else{
							$first[$key][] = $s;
							$first[$key][$s][] = $x;
						}
					} 
					
				} else { //se nao for terminal chama mesma funcao $s com o simbolo nao terminal
					getTerminalByNT($naoterminais, $s, $x, $t, $key, $first, $simbolos, $terminais);
					
				}
			}
		}
		return $first;
	}
	
	function getNT($simbolos){
		return (array_keys($simbolos));
	}
	
	function isNaoTerminal($naoterminais, $variavel){
		if (in_array($variavel, $naoterminais)) { 
			return true;
		}
	}
	
	function setFirstNT($naoterminais, $simbolos, $x, $t, $key, $first, $nt = null){
		foreach($naoterminais as $n){
			if(strpos($x, $n) === 0){
				if(strpos($simbolos[$n][0], $t) === 0){
					$first[$key][] = $t;
					$first[$key][$t][] = $x;
				} else{
					foreach($naoterminais as $nt){
						if(strpos($simbolos[$n][0], $nt) === 0){
							if(strpos($simbolos[$nt][0], $t) === 0){
								$first[$key][] = $t;
								$first[$key][$t][] = $x;
							} else{
								foreach($naoterminais as $nt1){
									if(strpos($simbolos[$n][0], $nt1) === 0){
										if(strpos($simbolos[$nt1][0], $t) === 0){
											$first[$key][] = $t;
											$first[$key][$t][] = $x;
										} else{
									
										}
									}
								}
							}
						}
					}
				}
			}
		}
		return $first;
	}
	
	function getFollow($simbolos, $terminais, $first){
		$follow = [];
		$naoTerminais = getNT($simbolos);
		$posicaoVazio = "";		
		usort($terminais,'organizarArraySimbolos'); //Organiza os simbolos terminais do maior para o menor
		$follow[$naoTerminais[0]][] = "$"; //Coloca o simbolo $ no primeiro follow
		
		foreach($naoTerminais as $naoTerminal){			
			foreach($simbolos as $ladoEsquerdo => $ladoDireito){
				foreach($ladoDireito as $ladoDireitoSimbolo){
					$posicaoSimbolo = stripos($ladoDireitoSimbolo,$naoTerminal); //Procura a posição do não-terminal
					$posicaoVazio = ""; 
					$simboloFollow = $posicaoSimbolo+strlen($naoTerminal);
					if($posicaoSimbolo){ //Se tiver achado o não-terminal
						if(empty($ladoDireitoSimbolo[$simboloFollow])){ //Se a proxima posição for vazia
							$follow[$naoTerminal] = $follow[$ladoEsquerdo]; //Pega o follow do lado esquerdo e vai para a proxima sentença
							continue;
						} //Se o proximo simbolo for um não-terminal e se não for vazio
						if(ctype_upper($ladoDireitoSimbolo[$simboloFollow]) && $ladoDireitoSimbolo[$simboloFollow] != 'X'){
							if($ladoDireitoSimbolo[$simboloFollow+1] == "'"){
								$simboloLinha = "'";
							}	
							$posicaoVazio = array_search('X',$first[$ladoDireitoSimbolo[$simboloFollow].$simboloLinha]); //Procura se tem alguma de vazio
							$follow[$naoTerminal] = $first[$ladoDireitoSimbolo[$simboloFollow].$simboloLinha]; //Pega o first da sentença	
							if(!empty($posicaoVazio)){ //Se houver alguma sentença (Regra da sentença vazia First+Follow)
								unset($follow[$naoTerminal][$posicaoVazio]); //Tira a sentença vazia do follow que esta armazenando e adiciona o follow do simbolo
								$follow[$naoTerminal] = array_merge($follow[$ladoDireitoSimbolo[$simboloFollow].$simboloLinha],$follow[$naoTerminal]);
							}
							$simboloLinha = "";
						}else{ //Se for um terminal
							$simboloFollow = substr($ladoDireitoSimbolo,$posicaoSimbolo); //Reduz a sentença até o terminal
							foreach($terminais as $terminal){ 
								if(stripos($simboloFollow,$terminal)){ //Verifica qual é o terminal e armazena
									$follow[$naoTerminal][] = $terminal;
									break;
								}
							}
						}
					}
				}				
			}
		}
		return $follow;
	} 
	
	function geraTabela($first, $terminais, $naoterminais, $follow, $simbolos){
		$tabela = [];
        foreach ($first as $key =>  $f) {
			foreach($terminais as $t){
				if(isset($f[$t])){
					$tabela[$key][$t] = $key .' ->'.$f[$t][0];
				}
				else{
					$tabela[$key][$t] = null;
				}	
			}
        }
        foreach ($simbolos as $key => $s) {
        	$pos = $key;
    		foreach ($s as $key) {
    			if($key==='X'){
    				foreach ($follow[$pos] as $key => $fp) {
    					if ($fp[0]==='$') {
    						$x = 'x';
    					} else{
    						$x = $fp[0];
    					}
    					if (in_array($x, $terminais)) { 
    						$tabela[$pos][$x] = $pos .' ->X';
						}
    				}
					$tabela[$pos]['$'] = $pos .' ->X';
    			}
    		}
    	}
        geraTabela2($tabela, $terminais);
        return $tabela;
	}

	function geraTabela2($tabela, $terminais){
		echo '<br>';
		$html = '<table cellpadding="10" cellspacing="1" border="1">';
		$html .= '<tr><th></th>';
		foreach($terminais as $t){
			$html .= '<th>'.$t.'</th>'; 
		}
		$html .= '<th>$</th>'; 
        $html .= '</tr>';
        foreach ($tabela as $key =>  $f) {
			$html .= '<tr align="center">';
				$html .= '<td>' . $key . '</td>';
			foreach($terminais as $t){
				if(isset($f[$t])){
					$html .= '<td>' . $tabela[$key][$t] . '</td>';
				}
				else{
					$html .= '<td>' .''. '</td>';
				}
				
			}
			if(isset($tabela[$key]['$'])){
				$html .= '<td>' . $tabela[$key]['$'] . '</td>';
			}else{
				$html .= '<td>' .''. '</td>';
			}
			$html .= '</tr>';
        }
        $html .= '</table>';
        echo $html;
	}

    function geraLinhasTabelas($pilha,$entrada,$sentencaTabela){       
        $tabelaPreditivaTabular = "<tr><td>";
        foreach($pilha as $pilhaTabela){
            $tabelaPreditivaTabular .= $pilhaTabela;
        }
        $tabelaPreditivaTabular .= "</td><td>";                
        foreach($entrada as $entradaTabela){
            $tabelaPreditivaTabular .= $entradaTabela;
        }                
        $tabelaPreditivaTabular .= "</td><td>$sentencaTabela</td></tr>"; 
        return $tabelaPreditivaTabular;   
    }

	function reconheceEntrada($tabela,$entrada,$terminais,$simbolo_inicio){
		$entrada = explode(" ", $entrada);
		$pilha = array("$",$simbolo_inicio);
		$senteca = "";	
		
		while($e = current($entrada)){
			if(@$tabela[end($pilha)][$e] !== null){
				$sentenca = $tabela[end($pilha)][$e];
				$ultimoSimboloPilha = key($pilha);
				unset($pilha[$ultimoSimboloPilha]);
				if($sentenca[1] == "'"){
					$sentenca = substr($sentenca,5);
				}else{
					$sentenca = substr($sentenca,4);
				}
				for($simbolo = strlen($sentenca)-1; $simbolo >= 0; $simbolo--){
					if($sentenca[$simbolo] == "'"){
						$pilha1[] = $sentenca[$simbolo-1]."'";
						$simbolo--;
					}	
					elseif(ctype_upper($sentenca[$simbolo])){
						$pilha1[] = $sentenca[$simbolo];
					}else{
						foreach($terminais as $terminal){ 
							if(strpos($sentenca,$terminal) !== FALSE){ //Verifica qual é o terminal e armazena
								$pilha1[] = $terminal;
								$simbolo-=(strlen($terminal)-1);
								break;
							}
						}
					}									
				}
				foreach($pilha1 as $p1){
					$pilha[] = $p1;
				}
				$pilha1 = "";
				if(end($pilha) == "X"){
					end($pilha); 
					$key = key($pilha);
					unset($pilha[$key]);
					unset($pilha[$key-1]);					
				}
			}
			else{
				echo "Nao aceita";
				exit;
			}
			
			if(end($pilha) === "$" && $e === "$"){
				echo "Aceita";
				exit;
			}elseif(end($pilha) == $e){			
				end($pilha);         // move the internal pointer to the end of the array
				$key = key($pilha);
				unset($pilha[$key]);
				next($entrada);
			}			
		}
	}
?>
