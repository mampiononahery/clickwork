<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

//fonction pour le calcul simulation simple
// Mampionona ///

if(!function_exists("verifier_to_don")){
	function verifier_to_don($id_pret){
		$CI =& get_instance();
		$CI->load->model('demande_cred/pret_model', 'pret');
		$pret = $CI->pret->get_pret_by_id_garantie($id_pret);
		$CI->load->model('workflow/pret_workflow_transition_model', 'workflow_transition');
		$score = $pret[0]['id_pg_score'];
		$go_to_don = 0;
		$state = 0;
		$info_transition = $CI->workflow_transition->get_fin_historique_by_pret($id_pret);
		if($score == SCORE_VERT){
			if($info_transition[0]['group_fin'] == DFR_ID_GROUP || $info_transition[0]['group_fin'] == DR_ID_GROUP || $info_transition[0]['group_fin'] == DFR_ID_GROUP ){
				$go_to_don = 1;
				$state = 12;
				
			}
		}
		else{
			if($info_transition[0]['group_fin'] == DGAMP_ID || $info_transition[0]['group_fin'] == DFR_ID_GROUP || $info_transition[0]['group_fin'] == DR_ID_GROUP || $info_transition[0]['group_fin'] == DRJCC ){
				$go_to_don = 1;
				$state = 28;
			}
		}
		$data_retour['id_worfkow_state'] = $state;
		$data_retour['go_to_don'] = $go_to_don;
		//$data_retour['group'] = $info_transition[0]['group_fin'];
		return $data_retour;
	}
}

if(!function_exists("get_titre_avis")){
	function get_titre_avis($group_connect_id,$id_score_t){
		$CI =& get_instance();
		$CI->load->model('workflow/pret_workflow_model', 'workflow_table');
		if($id_score_t>0){
			$score = "V";
			if(!empty($id_score_t) && $id_score_t!= VERT){
				$score = "A";
			}
			$criteres= array("ugrp_id_source"=>$group_connect_id,
			"non"=>-1,
			"score"=>$score);
			$info_workflow = $CI->workflow_table->get_pret_workflow_avec_criteres($criteres);
			if(sizeof($info_workflow)>0){
				$titre = $info_workflow[0]['libelle'];
			}
			else{
				$titre ="Avis";
			}
		}
		else{
			$titre ="Avis";
		}
		
		return $titre;
		
	}
}
if(!function_exists("creation_piece_jointe")){
	function creation_piece_jointe($id_pret,$id_pg_score,$is_garantie = 0){
		$CI =& get_instance();
        $CI->load->model('piece_jointe/piece_jointe_obligatoire_model', 'piece_obligatoire');
		$CI->load->model('piece_jointe/piece_jointe_model', 'pret_piece_jointe');
		$insert_batch = $CI->piece_obligatoire->get_avec_condition($id_pret,$id_pg_score,$is_garantie);
		
		if(sizeof($insert_batch)>0 ){
			$CI->pret_piece_jointe->insert_batch_piece_jointe($id_pret,$insert_batch);
		}
		
	}
}
if(!function_exists("verification_droit_menu")){
	function verification_droit_menu($id_pret=0,$menu){
		$droit_menu = 1;
		if($menu!=""){
			$droit_menu = 0;
			switch($menu){
				case "client":
					if(is_privilege_menu("client#creation")){
						$droit_menu = 1;
					}
				break;
				case "emprunteur":
					if(is_privilege_menu("emprunteur#Modification")){
						$droit_menu = 1;
					}
				break;
				case "garantie":
					if(is_privilege_menu("garantie_admin#Modification")){
						$droit_menu = 1;
					}
				break;
				case "piece_jointe":
					if(is_privilege_menu("piece_jointe#Modification")){
						$droit_menu = 1;
					}
				break;
				case "anomalie":
					if(is_privilege_menu("reserve_anomalie#Modification")){
						$droit_menu = 1;
					}
				break;
			}
		}
		if($droit_menu && $id_pret>0){
			$CI =& get_instance();
			$CI->load->model('demande_cred/pret_model', 'pret');
			$info_pret = $CI->pret->get_pret_deblocage_uacc_creer($id_pret);
			$info_connecte = get_info_user_connect();
			if($info_connecte[0]->uacc_group_fk == $info_pret[0]["unite_actuelle"] && $info_connecte[0]->uacc_id == $info_pret[0]["uacc_id_actuel"] ){
				$droit_menu = 1;
			}
			else{
				$droit_menu = 0;
			}
		}
		return $droit_menu;
	}
}
if(!function_exists("get_garantie_pret")){
	function get_garantie_pret($id_pret){
		$CI =& get_instance();
        $CI->load->model('garantie/pret_garantie_model', 'garantie');
		$criteres = array("id_pret"=>$id_pret);
		$is_garantie=  $CI->garantie->get_garantis_exists($criteres);
		if($is_garantie>0){
			return 1;
		}
		return 0;
	}
}

if(!function_exists("calcul_frais_dossier")){
	function calcul_frais_dossier($id_pret,$capital=0,$duree = 0,$taux_produit=0,$taux_reduction = 0,$plafond= 0,$plancher=0){
		$frais_temp = $taux_produit*$capital/100;
		
		$emp= get_employeur_pret($id_pret);
		if($emp['id_employeur']>0){
			$data_reduction = calcul_reduction_frais_dossier($emp['id_employeur'],$capital,$duree,$emp['cap']);
			$tx_redu = $taux_produit;
			if($data_reduction['taux_reduction']>0){
				$tx_redu = $taux_produit - $data_reduction['taux_reduction'];
			}
			$frais_temp =$tx_redu*$capital/100;
			if($data_reduction['reduction_moins_pourcentage']>0){
				$frais_reduction = ($frais_temp*$data_reduction['reduction_moins_pourcentage'])/100;
				$frais_temp  = $frais_temp - $frais_reduction;
			}
			if($data_reduction['reduction_montant']>0){
				$frais_temp = $frais_temp - $data_reduction['reduction_montant'];
			}
			
		}
		if($taux_reduction>0){
			$frais_reduction = ($frais_temp*$taux_reduction)/100;
			$frais_temp  = $frais_temp - $frais_reduction;
		}
		
		if($frais_temp<$plancher){
			return $plancher;
		}
		if($frais_temp> $plafond){
			return $plafond;
		}
		return $frais_temp;
		
	}
	
}

if(!function_exists("get_employeur_pret")){
	function get_employeur_pret($id_pret){
		$CI =& get_instance();
		$CI->load->model('base_rcx/base_rcx_model', 'rcx');
		$data_employeur = $CI->rcx->get_employeur_by_id_pret($id_pret);
		$data_retour= array();
		$data_retour['id_employeur'] =$data_employeur[0]['id_rcx_employeur'];
		$data_retour['cap'] = $data_employeur[0]['id_pg_nature_cap'];
		return $data_retour;
		
	}
}


if(!function_exists("calcul_reduction_frais_dossier")){
	function calcul_reduction_frais_dossier($id_rcx_employeur,$montant = 0,$duree =0,$nature_cap=0){
		$CI =& get_instance();
		$CI->load->model('base_rcx/base_rcx_model', 'rcx');
		$data = $CI->rcx->get_reduction_employeur_by_id($id_rcx_employeur);
		$data_retour = array();
		$taux_reduction = 0;
		$reduction_moins_pourcentage = 0;
		$reduction_montant = 0;
		
		if($data[0]['is_conventionne'] == 1){
			// frais de dossier //
			if($nature_cap == CAP){
				$cap_frais_dossier 				= $data[0]['conv_frais_dossier'];
				$taux_reduction =$cap_frais_dossier;
				$reduction_moins_pourcentage 	= $cap_frais_dossier;
				if($data[0]['type_reduction_frais_dossier_cap']== 1){
					$reduction_moins_pourcentage 	= $cap_frais_dossier;
					$taux_reduction = 0;
				}
				$cap_immo_frais_dossier = $data[0]['conv_cap_immo_frais_dossier'];
				if($duree <= 11){
					$taux_reduction = $data[0]['conv11_mois_reduction'];
					if($data[0]['conv11_mois_reduction_type']==1){
						$reduction_moins_11 = $taux_reduction;
						$taux_reduction = 0;
						//$reduction_moins_pourcentage = max($reduction_moins_11,$reduction_moins_pourcentage);
						$reduction_moins_pourcentage = $reduction_moins_pourcentage + $reduction_moins_11;
						
					}
					//reduction si inferieur au seuil 
					if($montant< $data[0]['conv11_mois_mt_credit']){
						$reduction_montant = $data[0]['conv11_mois_reduction_si_credit_inf'];
					}
				}
				else if($duree>=12 && $duree<=24){
					$taux_reduction = $data[0]['conv12_24_mois_reduction'];
					if($data[0]['conv12_24_mois_reduction_type']==1){
						$reduction_moins_12_24 = $taux_reduction;
						$taux_reduction = 0;
						//$reduction_moins_pourcentage = max($reduction_moins_11,$reduction_moins_pourcentage);
						$reduction_moins_pourcentage = $reduction_moins_pourcentage + $reduction_moins_12_24;
						
					}
					if($montant< $data[0]['conv12_24_mois_mt_credit']){
						$reduction_montant = $data[0]['conv12_24_mois_reduction_si_credit_inf'];
					}
				}
				else if($duree>=25 && $duree<=60){
					$taux_reduction = $data[0]['conv25_60_mois_reduction'];
					if($data[0]['conv25_60_mois_reduction_type']==1){
						$reduction_moins_25_60 = $taux_reduction;
						$taux_reduction = 0;
						//$reduction_moins_pourcentage = max($reduction_moins_11,$reduction_moins_pourcentage);
						$reduction_moins_pourcentage = $reduction_moins_pourcentage + $reduction_moins_12_24;
						
					}
					if($montant< $data[0]['conv25_60_mois_mt_credit']){
						$reduction_montant = $data[0]['conv25_60_mois_reduction_si_credit_inf'];
					}
				}
			}
			
		}
		
		$data_retour["taux_reduction"] = $taux_reduction;
		$data_retour["reduction_moins_pourcentage"] = $reduction_moins_pourcentage;
		$data_retour["reduction_montant"] = $reduction_montant;
		return $data_retour;
		
	}
}
if(!function_exists("get_info_pret_par_id")){
	function get_info_pret_par_id($id_pret = 0){
		$CI =& get_instance();
		$CI->load->model('demande_cred/pret_model', 'pret');
		$criteres = array("id_pret"=>$id_pret);
		$info_pret = $CI->pret->get_pret_client_info($criteres);
		return $info_pret[0];
	}
}
if(!function_exists("calcul_echeance")){
	function calcul_echeance($montant , $periode ,$taux_ann){
		$taux_mens = $taux_ann / 1200;
		$numerateur =  $taux_mens * $montant;
		$denominateur = 1 - ( 1/(pow(1+$taux_mens , $periode)) );
		 
		if($denominateur > 0  )
        $echeance = $numerateur / $denominateur ;
        
        else $echeance = 0;
		return $echeance ;
	}
}

if(!function_exists("calcul_echeance_diff")){
	function calcul_echeance_diff($montant , $periode ,$taux_ann, $tx_ht){
		$taux_mens = ($taux_ann + $tx_ht) / 1200;
		$numerateur =  $taux_mens * $montant;
		$denominateur = 1 - ( 1/(pow(1+$taux_mens , $periode)) );
			
		$echeance = $numerateur / $denominateur ;
		return $echeance ;
	}
}

	if(!function_exists("get_info_user_connect")){
		function get_info_user_connect(){
			$CI =&get_instance();
			$CI->load->library('flexi_auth');
			return $CI->flexi_auth->get_user_by_id($CI->flexi_auth->get_user_id())->result();
		}
	}
	if(!function_exists("get_users_affectation")){
		function get_users_affectation($uacc_id = 0){
			$CI =&get_instance();
			$CI->load->model('agence/affectation_reseau_model', 'affectation');
			$data_users = $CI->affectation->get_users_affectation($uacc_id);
			return $data_users;
		}
	}
if(!function_exists("get_tous_privilege")){
	function get_tous_privilege($sql_select=false,$sql_where=false){
		$CI =&get_instance();
		$CI->load->library('flexi_auth');
		if(!$sql_select){
			$sql_select = array(
				'upriv_id', 
				'upriv_name', 
			);
		}
		if(!$sql_where){
			$sql_where = array("upriv_users_uacc_fk"=>get_user_connect());
		}
		$data_privi=$CI->flexi_auth->get_user_privileges($sql_select,$sql_where);
		return $data_privi->result();
	}
}
if(!function_exists("echo_if_allowed")){
	function echo_if_allowed($desc_nom,$html){
		$CI =&get_instance();
		$CI->load->library('flexi_auth');
		$nom_priv= array($desc_nom);
		$priv = $CI->flexi_auth->is_privileged($nom_priv);
		echo $priv ? $html : "";
	}

}
// return true ou false;
if(!function_exists("is_privilege_menu")){
	function is_privilege_menu($priv_name){
		$CI =&get_instance();
		$CI->load->library('flexi_auth');
		$priv_name_array= array($priv_name);
		$return = $CI->flexi_auth->is_privileged($priv_name_array);
		return $return;
	}
}
if(!function_exists("get_user_connect")){
	function get_user_connect(){
		$CI =&get_instance();
		$CI->load->library('flexi_auth');
		return $CI->flexi_auth->get_user_id();
		
	}
	
}
if(!function_exists("formatter_group_ldap")){
	function formatter_group_ldap($group_non_formatter){
		$tableau_group= explode(",",$group_non_formatter);
		$cn1=$tableau_group[0];
		$cn1=explode("=",$cn1);
		$gnom=$cn1[1];
		return $gnom;
	}
}
if(!function_exists("get_group_user_connect")){
	function get_group_user_connect($id_group=false)
	{
		$CI =&get_instance();
		$CI->load->library('flexi_auth');
		if($id_group){
			return $CI->flexi_auth->get_user_group_id();
		}
		return $CI->flexi_auth->get_user_group();
	}
}
/*boucle formulaire administrative*/
if (!function_exists('form_administrative_simple')) {
	function form_administrative_simple($label , $id1 , $value1,$tabindex)
	{
		echo'
				<div class="row">
							<div class="col-md-2 right"> '.
							t($label).
							'* :</div>
							<div class="col-md-4 left">
								<input type=\'text\' class="form-control modifier_admin"  id="'
									.$id1.'"  name="' .$id1. '" value="' .$value1. '" tabindex="'.$tabindex.'"/>
							</div>
							
						</div>



				';
	}
}

if (!function_exists('form_administrative')) {
	function form_administrative($label , $id1, $value1 , $value2,$tab_index1=2,$tab_index2=4 )
	{
		if($id1=="li_fct_conjoint" || $id1=="li_nom_conjoint" || $id1=="li_emp_conjoint"){
			echo'
				<div class="row">
							<div class="conj_emp col-md-2 right"> '.
										t($label).
										'* :</div>
							<div class=" col-md-4 left">
								<input type=\'text\' class="form-control modifier_admin"  id="'
												.$id1.'"   name="' .$id1. '"  value="' .$value1. '" tabindex="'.$tab_index1.'"/>
							</div>
							<div class="col-md-1 acacher ">|</div>
							<div class="col-md-4 acacher left">
								<input type=\'text\' class="form-control modifier_admin"  id="'.$id1.'_co" name="' .$id1. '_co" value="' .$value2. '" tabindex="'.$tab_index2.'"/>
							</div>
						</div>
			
			
			
				';
		}
		else{
		echo'
				<div class="row">
							<div class="col-md-2 right"> '.
							t($label).
				'* :</div>
							<div class="col-md-4 left">
								<input type=\'text\' class="form-control modifier_admin"  id="'
								.$id1.'"   name="' .$id1. '"  value="' .$value1. '" tabindex="'.$tab_index1.'"/>
							</div>
							<div class="col-md-1 acacher">|</div>
							<div class="col-md-4 acacher left">
								<input type=\'text\' class="form-control modifier_admin"  id="'.$id1.'_co" name="' .$id1. '_co" value="' .$value2. '" tabindex="'.$tab_index2.'"/>
							</div>
						</div>
				
				
				
				';
		}
	}
}

if (!function_exists('form_administrative_non_obl')) {
	function form_administrative_non_obl($label , $id1 , $value1 , $value2,$tab1=5,$tab2=5)
	{
		if($id1=="li_fct_conjoint" || $id1=="li_nom_conjoint" || $id1=="li_emp_conjoint"){
			echo'
				<div class="row">
							<div class="conj_emp col-md-2 right"> '.
									t($label).
									'* :</div>
							<div class=" col-md-4 left">
								<input type=\'text\' class="form-control modifier_admin"  id="'
											.$id1.'"   name="' .$id1. '"  value="' .$value1. '" tabindex="'.$tab1.'"/>
							</div>
							<div class="col-md-1 acacher ">|</div>
							<div class="col-md-4 acacher left">
								<input type=\'text\' class="form-control modifier_admin"  id="'.$id1.'_co" name="' .$id1. '_co" value="' .$value2. '" tabindex="'.$tab2.'"/>
							</div>
						</div>
		
		
		
				';
		}
		else{
			echo'
					<div class="row">
								<div class="col-md-2 right"> '.
								t($label).
								' :</div>
								<div class="col-md-4 left">
									<input type=\'text\' class="form-control modifier_admin"  id="'
										.$id1.'"  name="' .$id1. '"  value="' .$value1. '" tabindex="'.$tab1.'"/>
								</div>
								<div class="col-md-1 acacher">|</div>
								<div class="col-md-4 acacher left">
									<input type=\'text\' class="form-control modifier_admin"  id="' .$id1. '_co"  name="' .$id1. '_co" value="' .$value2. '" tabindex="'.$tab2.'"/>
								</div>
							</div>
	
	
	
					';
		}
	}
}

if (!function_exists('form_administrative_nb_simple')) {
	function form_administrative_nb_simple($label , $id1)
	{
		echo'
				<div class="row">
							<div class="col-md-2 right"> '.
							t($label).
							'* :</div>
							<div class="col-md-1">
								<input type=\'text\' class="form-control left modifier_admin"  id="'
									.$id1.'"  name="' .$id1. '"/>
							</div>
							
						</div>



				';
	}
}


if (!function_exists('form_administrative_nb')) {
	function form_administrative_nb($label , $id1 , $value1 , $value2,$tab1 = 2,$tab2 = 3 )
	{
		echo'
				<div class="row">
							<div class="col-md-2 right" id="id_lab"> '.
							t($label).
							'* :</div>
							<div class="col-md-1 left">
								<input type=\'text\' class="form-control modifier_admin"  id="'
									.$id1.'"  name="' .$id1. '" value="' .$value1. '" tabindex="'.$tab1.'"/>
							</div>
							<div class="col-md-3"></div>		
							<div class="col-md-1 acacher">|</div>
							<div class="col-md-1 acacher left">
								<input type=\'text\' class="form-control left modifier_admin" id="' .$id1. '_co"  name="' .$id1. '_co" value="' .$value2. '" tabindex="'.$tab2.'"/>
							</div>
				</div>



				';
	}
}
if (!function_exists('compte_emp')) {
	function compte_emp($label , $id1 , $value1 , $value2 )
	{
		echo'
				<div class="row">
							<div class="col-md-2 right" id="id_lab"> '.
							t($label).
							'* :</div>
							<div class="col-md-1 left">
								<input type=\'text\' class="form-control"  id="'
									.$id1.'"  name="' .$id1. '" value="' .$value1. '"/>
							</div>
							<div class="col-md-3"></div>		
							<div class="col-md-1 acacher">|</div>
							<div class="col-md-1 acacher left">
								<input type=\'text\' class="form-control left" id="' .$id1. '_co"  name="' .$id1. '_co" value="' .$value2. '"/>
							</div>
				</div>



				';
	}
}


if (!function_exists('form_administrative_num')) {
	function form_administrative_num($label, $id1 , $value1 , $value2,$tab1 = 2,$tab2 = 3)
	{
		if($label == "form_admin_N_matr"){
			echo'
				<div class="row">
						
							<div class="col-md-2 right"> '.
							t($label).
							':</div>
							<div class="col-md-2 left">
								<input type=\'text\' class="form-control left modifier_admin"   id="'
									.$id1.'"  name="' .$id1. '" value="' .$value1. '" tabindex="'.$tab1.'"/>
							</div>
							<div class="col-md-2"></div>
							<div class="col-md-1 acacher">|</div>
							<div class="col-md-2 acacher left">
								<input type=\'text\' class="form-control left modifier_admin" id="' .$id1. '_co"  name="' .$id1. '_co" value="' .$value2. '" tabindex="'.$tab2.'"/>
							</div>
						</div>



				';
		}
		else{
		
		echo'
				<div class="row">
						
							<div class="col-md-2 right"> '.
							t($label).
							'* :</div>
							<div class="col-md-2 left">
								<input type=\'text\' class="form-control left modifier_admin"   id="'
									.$id1.'"  name="' .$id1. '" value="' .$value1. '" tabindex="'.$tab1.'"/>
							</div>
							<div class="col-md-2"></div>
							<div class="col-md-1 acacher">|</div>
							<div class="col-md-2 acacher left">
								<input type=\'text\' class="form-control left modifier_admin" id="' .$id1. '_co"  name="' .$id1. '_co" value="' .$value2. '" tabindex="'.$tab2.'"/>
							</div>
						</div>



				';
				}
	}
}

if (!function_exists('form_administrative_num_non_obl')) {
	function form_administrative_num_non_obl($label , $id1 , $value1 , $value2,$tab1=2,$tab2=3)
	{
		echo'
				<div class="row">
							<div class="col-md-2 right"> '.
							t($label).
							' :</div>
							<div class="col-md-2 left">
								<input type=\'text\' class="form-control left modifier_admin"  id="'
									.$id1.'"  name="' .$id1. '" value="' .$value1. '" tabindex="'.$tab1.'"/>
							</div>
							<div class="col-md-2"></div>
							<div class="col-md-1 acacher">|</div>
							<div class="col-md-2 acacher left">
								<input type=\'text\' class="form-control left modifier_admin"  id="' .$id1. '_co"  name="' .$id1. '_co" value="' .$value2. '" tabindex="'.$tab2.'"/>
							</div>
						</div>



				';
	}
}

if (!function_exists('espace')) {
	function espace()
	{
		echo'
				      <div class="row">
                       <div class="col-md-3 right"><?php echo t(\' \');?></div>
                       </div>
                       <div class="row">
                       <div class="col-md-3 right"><?php echo t(\' \');?></div>
                       </div>
				<div class="row">
                       <div class="col-md-3 right"><?php echo t(\' \');?></div>
                       </div>
				<div class="row">
                       <div class="col-md-3 right"><?php echo t(\' \');?></div>
                       </div>
				<div class="row">
                       <div class="col-md-3 right"><?php echo t(\' \');?></div>
                       </div>

				';
	}
}

if (!function_exists('check_user_connected')) {
    function check_user_connected()
    {
        // IMPORTANT! This global must be defined BEFORE the flexi auth library is loaded!
        // It is used as a global that is accessible via both models and both libraries, without it, flexi auth will not work.
        $CI =& get_instance();
        $CI->auth = new stdClass;
        // Load 'standard' flexi auth library by default.
        $CI->load->library('flexi_auth');
        if (!$CI->flexi_auth->is_logged_in()) {
            // redirect('auth');
        }
    }
}

if (!function_exists('add_column_in_table')) {
    function add_column_in_table($nom_table, $nom_champ_old, $nom_champ, $type)
    {
        $CI =& get_instance();
        $sql = "SHOW COLUMNS FROM $nom_table LIKE '$nom_champ'";
        $result = $CI->db->query($sql)->num_rows();
        if ($result == 0) {
            $sql1 = "ALTER TABLE $nom_table ADD $nom_champ $type NULL";
            $query1 = $CI->db->query($sql1);
        }
        if ($result > 0) {
            $sql1 = "ALTER TABLE $nom_table CHANGE $nom_champ_old $nom_champ $type NULL";
            $query1 = $CI->db->query($sql1);
        }
    }
}

if (!function_exists('notification_cs')) {
    function notification_cs()
    {
        $CI =& get_instance();
        $CI->load->model('cas_speciaux/cs_cs_model', 'cs_cs');
        $compteur = $CI->cs_cs->lister_cs_notification_count();
        return $compteur;
    }
}

if (!function_exists('replace_caractere_speciaux')) {
    function replace_caractere_speciaux($original)
    {
        $pattern = array('-', '?', '.', ' ', '_', 'ê', 'ê', 'ê', 'ê', 'ê', 'ê', 'ê', 'ê', 'ê', 'ê');
        $patternReplace = array('', '', '', '', '', 'e', 'e', 'i', 'i', 'a', 'a', 'a', 'c', 'o', 'o');
        foreach ($pattern as $key => $value) {
            $original = str_replace($pattern[$key], $patternReplace[$key], $original);
        }
        $original = strtolower($original);
        return $original;
    }
}
if (!function_exists('retire_accents')) {
    function retire_accents($chaine)
    {
        $patterns = array('/[êêêêê]/i', '/[êêêê]/i', '/[ê]/i', '/[êêêê]/i', '/[ê]/i', '/[êêêêê]/i', '/[êêêê]/i', '/[êê]/i');
        $replacements = array('a', 'e', 'c', 'i', 'n', 'o', 'u', 'y');

        $chaine = preg_replace($patterns, $replacements, $chaine);
        return $chaine;

    }
}

if (!function_exists('compare_date')) {
    function compare_date($date1, $date2)
    {
        $ts1 = strtotime($date1);
        $ts2 = strtotime($date2);
        $seconds_diff = $ts1 - $ts2;
        if ($seconds_diff < 0) {
            return -1;
        } elseif ($seconds_diff == 0) {
            return 0;
        } else {
            return 1;
        }
    }
}

if (!function_exists('add_day_to_date')) {
    function add_day_to_date($date, $day, $format = "Y-m-d")
    {
        return date($format, strtotime($date . ' + ' . $day . ' days'));
    }
}

if (!function_exists('substract_day_to_date')) {
    function substract_day_to_date($date, $day, $format = "Y-m-d")
    {
        return date($format, strtotime($date . ' - ' . $day . ' days'));
    }
}

if (!function_exists('date_diff_day')) {
    function date_diff_day($date1, $date2)
    {
        return round(strtotime($date1) - strtotime($date2)) / (60 * 60 * 24);
    }
}


if (!function_exists('convertToDateMonthYearFrenchFromMysql')) {
    function convertToDateMonthYearFrenchFromMysql($date, $noDay = false)
    {
        //$date =  new DateTime($date);
        date_default_timezone_set('Europe/Paris');
        setlocale(LC_TIME, 'fr_FR.utf8', 'fra');// OK
        //return  utf8_encode(strftime("%d %B %Y",strtotime($date)));
        //CRA : tenir compte les dates avec accents
        $dateJour = date('d', strtotime($date));
        $dateMois = date('F', strtotime($date));
        $dateMois = convert_mois_en_to_fr($dateMois);
        $dateAnnee = date('Y', strtotime($date));
        if ($noDay) {
            $resDate = $dateMois . " " . $dateAnnee;
        } else {
            $resDate = $dateJour . " " . $dateMois . " " . $dateAnnee;
        }
        return $resDate;
    }
}

//enleve les esapces dans les nombres
if(!function_exists("number_format_en_nombre")){ 
	function number_format_en_nombre($str, $rep = " "){
		$str= str_replace($rep,"",$str);
		$nombre = floatval($str);
		return $nombre;
	}
}
if (!function_exists('convertToDateFrenchFormatFromMysql')) {
    function convertToDateFrenchFormatFromMysql($date)
    {
        //$date =  new DateTime($date);
        date_default_timezone_set('Europe/Paris');
        setlocale(LC_TIME, 'fr_FR.utf8', 'fra');// OK
        return utf8_encode(strftime("%d/%m/%y", strtotime($date)));
    }
}

if (!function_exists('convertToMonthYearFrenchFromMysql')) {
    function convertToMonthYearFrenchFromMysql($date)
    {
        date_default_timezone_set('Europe/Paris');
        setlocale(LC_TIME, 'fr_FR.utf8', 'fra');// OK
        $value = utf8_encode(strftime("%B %Y", strtotime($date)));
        return strtoupper(substr($value, 0, 1)) . substr($value, 1);

    }
}

if (!function_exists('date_fr_to_en')) {
    function date_fr_to_en($date_to_convert, $separator_fr = "/", $separtor_en = "-")
    {
        if ($date_to_convert && isset($date_to_convert)) {
            $tab = explode($separator_fr, $date_to_convert);
            if (count($tab) == 3) {
                $res = $tab[2] . $separtor_en . $tab[1] . $separtor_en . $tab[0];
                return $res;
            }
        }
        return $date_to_convert;
    }
}

if (!function_exists('date_en_to_fr')) {
    function date_en_to_fr($date_to_convert, $separator_en = "-", $separtor_fr = "/")
    {
        if ($date_to_convert && isset($date_to_convert)) {
            $tab = explode($separator_en, $date_to_convert);
            if (count($tab) == 3) {
                $res = $tab[2] . $separtor_fr . $tab[1] . $separtor_fr . $tab[0];
                return $res;
            }
        }
        return $date_to_convert;
    }
}
if (!function_exists('convert_mois_en_to_fr')) {
    function convert_mois_en_to_fr($mois_en)
    {
        switch ($mois_en) {
            case 'January':
                return 'Janvier';
            case 'February':
                return 'Fêvrier';
            case 'March':
                return 'Mars';
            case 'April':
                return 'Avril';
            case 'May':
                return 'Mai';
            case 'June':
                return 'Juin';
            case 'July':
                return 'Juillet';
            case 'August':
                return 'Aoêt';
            case 'September':
                return 'Septembre';
            case 'October':
                return 'Octobre';
            case 'November':
                return 'Novembre';
            case 'December':
                return 'Dêcembre';
        }
    }
}

if (!function_exists('get_mois_fr_court')) {
    function get_mois_fr_court($mois)
    {
        switch ($mois) {
            case 1:
                return 'Janv';
            case 2:
                return 'F&eacute;v';
            case 3:
                return 'Mars';
            case 4:
                return 'Avril';
            case 5:
                return 'Mai';
            case 6:
                return 'Juin';
            case 7:
                return 'Juil';
            case 8:
                return 'Ao&ucirc;t';
            case 9:
                return 'Sept';
            case 10:
                return 'Oct';
            case 11:
                return 'Nov';
            case 12:
                return 'D&eacute;c';
        }
    }
}

if (!function_exists('get_mois_fr')) {
    function get_mois_fr($mois)
    {
        switch ($mois) {
            case 1:
                return 'Janvier';
            case 2:
                return 'F&eacute;vrier';
            case 3:
                return 'Mars';
            case 4:
                return 'Avril';
            case 5:
                return 'Mai';
            case 6:
                return 'Juin';
            case 7:
                return 'Juillet';
            case 8:
                return 'Ao&ucirc;t';
            case 9:
                return 'Septembre';
            case 10:
                return 'Octobre';
            case 11:
                return 'Novembre';
            case 12:
                return 'D&eacute;cembre';
        }
    }
}
if (!function_exists('convert_jour_en_to_fr')) {
    function convert_jour_en_to_fr($day_en){
        switch ($day_en) {
            case 'Monday':
                return 'Lundi';
            case 'Tuesday':
                return 'Mardi';
            case 'Wednesday':
                return 'Mercredi';
            case 'Thursday':
                return 'Jeudi';
            case 'Friday':
                return 'Vendredi';
            case 'Saturday':
                return 'Samedi';
            case 'Sunday':
                return 'Dimanche';
        }
    }
}
if (!function_exists('convert_jour_fr_to_en')) {
    function convert_jour_fr_to_en($day_fr)
    {
        $day_en = '';
        if (strtolower($day_fr) == 'lundi') {
            $day_en = 'Monday';
        } else if (strtolower($day_fr) == 'mardi') {
            $day_en = 'Tuesday';
        } else if (strtolower($day_fr) == 'mercredi') {
            $day_en = 'Wednesday';
        } else if (strtolower($day_fr) == 'jeudi') {
            $day_en = 'Thursday';
        } else if (strtolower($day_fr) == 'vendredi') {
            $day_en = 'Friday';
        } else if (strtolower($day_fr) == 'samedi') {
            $day_en = 'Saturday';
        } else if (strtolower($day_fr) == 'dimanche') {
            $day_en = 'Sunday';
        }
        return $day_en;
    }
}

if (!function_exists('convert_array_jour_fr_to_en')) {
    function convert_array_jour_fr_to_en($array_day_fr)
    {
        $ret = array();
        if ($array_day_fr) {
            foreach ($array_day_fr as $value) {
                array_push($ret, convert_jour_fr_to_en($value));
            }
        }
        return $ret;
    }
}

if (!function_exists('convert_array_jour_en_to_fr')) {
    function convert_array_jour_en_to_fr($array_day_en)
    {
        $ret = array();
        if ($array_day_en) {
            foreach ($array_day_en as $value) {
                array_push($ret, convert_jour_en_to_fr($value));
            }
        }
        return $ret;
    }
}

if (!function_exists('get_day_name_by_date')) {
    function get_day_name_by_date($date)
    {
        if ($date && !empty($date)) {
            return convert_jour_en_to_fr(date('l', strtotime($date)));
        }
        return $date;
    }
}

if (!function_exists('check_array_key_has_value')) {
    function check_array_key_has_value($tab, $key)
    {
        return array_key_exists($key, $tab) && $tab[$key];
    }
}

if (!function_exists('get_file_extension')) {
    function get_file_extension($image_name)
    {
        $image = explode(".", $image_name);
        return strtolower(end($image));
    }
}

if (!function_exists('remove_duplicate_string')) {
    function remove_duplicate_string($string)
    {
        $strtab = explode(' ', $string);
        $strtab = array_unique($strtab);
        return implode(' ', $strtab);
    }
}


if (!function_exists('deteleAllFilesInDirectory')) {
    function  deteleAllFilesInDirectory($directory)
    {
        $files = glob("{$directory}/*"); // get all file names
        foreach ($files as $file) { // iterate files
            if (is_file($file))
                unlink($file); // delete file
        }
    }
}


if (!function_exists('get_dropdown_option_array')) {
    function get_dropdown_option_array($objectList, $value, $text, $placeholder = '')
    {
        $select_option = !empty($placeholder) ? array('' => $placeholder) : array();
        if ($objectList) {
            foreach ($objectList as $object) {
                $select_option[$object->{$value}] = $object->{$text};
            }
        }
        return $select_option;
    }
}

if (!function_exists('send_email_igy')) {
    function send_email_igy($from_email, $to_email, $from_name, $template, $subject, $data)
    {
        $CI = &get_instance();
        $CI->email->clear();
        $CI->email->from($from_email, $from_name);
        $CI->email->to($to_email);
        $CI->email->subject($subject);
        $CI->email->message($CI->load->view($template, $data, true));
        return $CI->email->send();
    }
}
if(!function_exists('day_en_lettre_edition')){
	function day_en_lettre_edition(){
		$date = date("d/m/Y");
		$temp = explode("/",$date);
		$mois = $temp[1];
		$jour = $temp[0];
		$annee = $temp[2];
		$newTimestamp = mktime(12,0,0,$mois,$jour,$annee);
		$tab_jour = array("Dimanche","Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi");
		$tab_mois = array("","Janvier","Février","Mars","Avril","Mai","Juin","Juillet","Août","Septembre","Octobre","Novembre","Décembre");
		
		$j_l = $tab_jour[date("w", $newTimestamp)];
		$m_l = $tab_mois[date("n", $newTimestamp)];
		
		$lettre = $j_l ." ".$jour." ".$m_l." ".$annee;
		return $lettre;
	
	}
}
if (!function_exists('send_email_without_template')) {
    function send_email_without_template($from_email, $to_email, $from_name, $subject, $message)
    {
        $CI = &get_instance();
        $CI->email->clear();
        $CI->email->from($from_email, $from_name);
        $CI->email->to($to_email);
        $CI->email->subject($subject);
        $CI->email->message($message);
		$CI->email->set_mailtype("html");
		return $CI->email->send();
    }
}

if (!function_exists('in_array_object')) {
    function in_array_object($objectList, $id_name, $id_value)
    {
        $found = false;
        foreach ($objectList as $object) {
            if ($object->{$id_name} == $id_value) {
                $found = true;
                break;
            }
        }
        return $found;
    }
}

if (!function_exists('group_array_object_by_key')) {
    function group_array_object_by_key($old_arr, $key_to_grouped)
    {
        $array_grouped = array();
        foreach ($old_arr as $key => $item) {
            $array_grouped[$item->{$key_to_grouped}][$key] = $item;
        }
        return $array_grouped;
    }
}

if (!function_exists('is_paire')) {
    function is_paire($num)
    {
        if ($num % 2 == 0) {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('get_object_ucfirst_list')) {
    function get_object_ucfirst_list($object_list, $key_to_update)
    {
        foreach ($object_list as $object) {
            $object->{$key_to_update} = ucfirst($object->{$key_to_update});
        }
        return $object_list;
    }
}
if (!function_exists('format_millier_perso')) {
	function format_millier_perso($str)
	{
		$j = strpos($str,".");
		if($j!=0){
			$rest = substr($str,$j);
			$str = substr($str,0,$j);
		}
		$count = 1;
		$result = "";
		for($i=strlen($str)-1;$i>=0;$i--)
		{
			$result.=$str[$i];
			if($count%3==0){
				$result.= " ";
			}
			$count++;
		}
		if($j!=0) return strrev($result).substr($rest,0,3);
		return strrev($result);
	}
}
if (!function_exists('recherche_texte')) {
	function recherche_texte($str,$s_chaine)
	{
		$res = strchr(strtolower($str),strtolower($s_chaine));
		if($res!=null) return true;
		else return false;
	}
}
if (!function_exists('get_object_id_list')) {
    function get_object_id_list($object_list, $id = 'id')
    {
        $object_id_list = array();
        if ($object_list && count($object_list) > 0) {
            foreach ($object_list as $object) {
                array_push($object_id_list, $object->{$id});
            }
        }
        return $object_id_list;
    }
}

if (!function_exists('get_array_ucfirst_list')) {
    function get_array_lcfirst_list($array)
    {
        foreach ($array as $elt) {
            $elt = lcfirst($elt);
        }
        return $array;
    }
}

if (!function_exists('get_footer_text')) {
    function get_footer_text()
    {
        $CI =& get_instance();
        $CI->load->model('textes_model');
        $textes = $CI->textes_model->get_all_textes_footer();
        return $textes;
    }
}

if (!function_exists('decrypter')) {
    function decrypter($maChaineCrypter)
    {
        $maCleDeCryptage = "<45ê6%vtxê";
        $maCleDeCryptage = md5($maCleDeCryptage);
        $letter = -1;
        $newstr = "";
        // $maChaineCrypter = base64_decode($maChaineCrypter);
        $key = "sdf*007!";
        $td = mcrypt_module_open(MCRYPT_DES, "", MCRYPT_MODE_ECB, "");
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key, $iv);
        $data = mdecrypt_generic($td, base64_decode($maChaineCrypter));
        mcrypt_generic_deinit($td);
        if (substr($data, 0, 1) != '!')
            return false;
        $data = substr($data, 1, strlen($data) - 1);
        $maChaineCrypter = unserialize($data);
        $strlen = strlen($maChaineCrypter);
        for ($i = 0; $i < $strlen; $i++) {
            $letter++;
            if ($letter > 31) {
                $letter = 0;
            }
            $neword = ord($maChaineCrypter{$i}) - ord($maCleDeCryptage{$letter});
            if ($neword < 1) {
                $neword += 256;
            }
            $newstr .= chr($neword);
        }
        return $newstr;
    }
}
if (!function_exists('crypter')) {
    function crypter($maChaineACrypter)
    {
        $maCleDeCryptage = "<45ê6%vtxê";
        $maCleDeCryptage = md5($maCleDeCryptage);
        $letter = -1;
        $newstr = "";
        $strlen = strlen($maChaineACrypter);
        for ($i = 0; $i < $strlen; $i++) {
            $letter++;
            if ($letter > 31) {
                $letter = 0;
            }
            $neword = ord($maChaineACrypter{$i}) + ord($maCleDeCryptage{$letter});
            if ($neword > 255) {
                $neword -= 256;
            }
            $newstr .= chr($neword);
        }
        $key = "sdf*007!";  // Clê de 8 caractêres max
        $data = serialize($newstr);
        $td = mcrypt_module_open(MCRYPT_DES, "", MCRYPT_MODE_ECB, "");
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key, $iv);
        $data = base64_encode(mcrypt_generic($td, '!' . $data));
        mcrypt_generic_deinit($td);
        return $data;
    }
}

if (!function_exists('sortArrayofObjectByProperty')) {
    /**
     * Sort one array of objects by one of the object's property
     *
     * @param mixed $array , the array of objects
     * @param mixed $property , the property to sort with
     * @return mixed, the sorted $array
     */
    function sortArrayofObjectByProperty($array, $property, $order = "ASC")
    {
        $cur = 1;
        $stack[1]['l'] = 0;
        $stack[1]['r'] = count($array) - 1;

        do {
            $l = $stack[$cur]['l'];
            $r = $stack[$cur]['r'];
            $cur--;

            do {
                $i = $l;
                $j = $r;
                $tmp = $array[(int)(($l + $r) / 2)];

                // split the array in to parts
                // first: objects with "smaller" property $property
                // second: objects with "bigger" property $property
                do {
                    while ($array[$i]->{$property} < $tmp->{$property}) $i++;
                    while ($tmp->{$property} < $array[$j]->{$property}) $j--;

                    // Swap elements of two parts if necesary
                    if ($i <= $j) {
                        $w = $array[$i];
                        $array[$i] = $array[$j];
                        $array[$j] = $w;

                        $i++;
                        $j--;
                    }

                } while ($i <= $j);

                if ($i < $r) {
                    $cur++;
                    $stack[$cur]['l'] = $i;
                    $stack[$cur]['r'] = $r;
                }
                $r = $j;

            } while ($l < $r);

        } while ($cur != 0);

        // Added ordering.
        if ($order == "DESC") {
            $array = array_reverse($array);
        }
        return $array;
    }
}

if (!function_exists('validateDate')) {
    function validateDate($date, $format = 'd/m/Y')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
}

if (!function_exists('echo_if_alloweds')) {
    function echo_if_alloweds($priv_desc, $html)
    {
        $CI =& get_instance();
        $CI->load->library('flexi_auth');
        return $CI->flexi_auth->is_privileged($priv_desc) ? $html : '';
    }
}

if (!function_exists('date_utils')) {
    function date_utils($date, $format = 'Y-m-d')
    {
        $CI =& get_instance();
        $CI->load->library('DateUtils');
        $date = new DateTime($date);
        return $CI->dateutils->datefr(strtotime($date->format($format)), 'n', 'n', 'n');

    }
}



if (!function_exists('getStartAndEndDate')) {
    function getStartAndEndDate($week, $year)
    {
        $dto = new DateTime();
        $dto->setISODate($year, $week);
        $ret['week_start'] = $dto->format('Y-m-d');
        $dto->modify('+6 days');
        $ret['week_end'] = $dto->format('Y-m-d');
        return $ret;
    }
}


if (!function_exists('set_value')) {
    function set_value($field = '', $default = '')
    {
        $OBJ = &_get_validation_object();

        if ($OBJ === TRUE && isset($OBJ->_field_data[$field])) {
            return form_prep($OBJ->set_value($field, $default));
        } else {
            if (!isset($_POST[$field])) {
                return $default;
            }
            return form_prep($_POST[$field]);
        }
    }
}

if (!function_exists('verification_excel')) {
    function verification_excel($import_obj_phpExcel, $model_xls, $ligne, $highestColumnIndex)
    {
        //$CI->load->library('excel');
        $model_phpExcel = PHPExcel_IOFactory::load($model_xls);
        $import_worksheet = $import_obj_phpExcel->setActiveSheetIndex(0);
        $model_worksheet = $model_phpExcel->setActiveSheetIndex(0);
        $same = true;
        for ($col = 1; $col < $highestColumnIndex; $col++) {
            $cell = $model_worksheet->getCellByColumnAndRow($col, $ligne);
            $val_cell_model = $cell->getValue();
            $cell = $import_worksheet->getCellByColumnAndRow($col, $ligne);
            $val_cell_import = $cell->getValue();
            if (trim($val_cell_import) != trim($val_cell_model)) $same = false;
        }
        // unset($import_worksheet);
        // unset($model_xls);
        return $same;
    }
}

if (!function_exists('array_to_csv')) {
    function array_to_csv($array, $download = "")
    {
        $download = "sql/archive_csv/" . $download;
        if ($download != "") {
            header('Content-Type: application/csv');
			
            header('Content-Disposition: attachement; filename="' . $download . '"');
        }
        ob_start();
        $f = fopen($download, 'wb') or show_error("Can't open php://output");
        $n = 0;
        foreach ($array as $line) {
            $n++;
            if (!fputcsv($f, $line, ";")) {
                show_error("Can't write line $n: $line");
            }
        }
        fclose($f) or show_error("Can't close php://output");
        $str = ob_get_contents();
        ob_end_clean();
        return $str;
    }
}

if (!function_exists('query_to_csv')) {
    function query_to_csv($query, $headers = TRUE, $download = "")
    {
        if (!is_object($query) OR !method_exists($query, 'list_fields')) {
            show_error('invalid query');
        }

        $array = array();

        if ($headers) {
            $line = array();
            foreach ($query->list_fields() as $name) {
                $line[] = $name;
            }
            $array[] = $line;
        }

        foreach ($query->result_array() as $row) {
            $line = array();
            foreach ($row as $item) {
                $line[] = $item;
            }
            $array[] = $line;
        }

        echo array_to_csv($array, $download);
    }
}

if (!function_exists('encrypt_password')) {
    function encrypt_password($plain_text)
    {
        $chars = str_split($plain_text);
        $crypt = array();
        foreach ($chars as $char) {
            $crypt[] = ord($char);
        }
        $encrypted = implode(':', $crypt);
        return $encrypted;
    }
}

if (!function_exists('decrypt_password')) {
    function decrypt_password($ciphered_text)
    {
        $asc = explode(':', $ciphered_text);
        $decrypt = array();
        foreach ($asc as $ascii) {
            $decrypt[] = chr((int)$ascii);
        }
        return implode('', $decrypt);
    }
}

if (!function_exists('recursiveRemovalObject')) {
    function recursiveRemovalObject($array, $val, $keyRef)
    {
        $subArray = array();
        if (is_array($array)) {
            foreach ($array as $key => &$arrayElement) {
                if ($arrayElement->$keyRef && $arrayElement->$keyRef == $val) {
                    $subArray[] = $arrayElement;
                } else {
                    unset($arrayElement);
                }

            }
        }
        return $subArray;
    }
}

if (!function_exists('do_export_commun_feuille')) {

    function do_export_commun_feuille(&$objPHPExcel, $array_header, $titre, $contenu, $startLineTitle = 2, $startLineHeader = 4, $startLineBody = 5, $nomFeuille = 'Feuille 1', $sheetId = 0, $custom_entete = array(), $line_info = array())
    {
        $objPHPExcel->getProperties()->setTitle("title")->setDescription("description");
        $objPHPExcel->setActiveSheetIndex($sheetId);
        $objPHPExcel->getActiveSheet()->setTitle($nomFeuille);
        $debut = PHPExcel_Cell::stringFromColumnIndex(0);
        $fin = PHPExcel_Cell::stringFromColumnIndex(count($array_header));

        if (!empty($titre)) {
            //Le titre commence par la ligne 2
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow('0', $startLineTitle, $titre);
            //Former par exemple : 'A2:E2'
            $colonne = $debut . $startLineTitle . ':' . $fin . $startLineTitle;
            $objPHPExcel->getActiveSheet()->mergeCells($colonne);
            $objPHPExcel->getActiveSheet()->getStyle($colonne)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle($colonne)->getFont()->setSize(18);
            $objPHPExcel->getActiveSheet()->getStyle($colonne)->getFont()->setBold(TRUE);
        }
		/* if (!empty($titre)) {
			 $startLineTitle++;
            //Le titre commence par la ligne 2
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow('0', $startLineTitle, "Date de réception accord Aro");
			 $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow('1', $startLineTitle, "");
			 $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow('2', $startLineTitle, "Date de réception accord Aro");
			 $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow('3', $startLineTitle, "");
			  $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow('4', $startLineTitle, "Date de réception accord Aro");
			 $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow('5', $startLineTitle, "");
            //Former par exemple : 'A2:E2'
           
        }*/

        //Style pour les titres
        $colonne = $debut . $startLineHeader . ':' . $fin . $startLineHeader;
        $objPHPExcel->getActiveSheet()->getStyle($colonne)->getFont()->setBold(TRUE);
        $objPHPExcel->getActiveSheet()->getStyle($colonne)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        /*
		 * Ajout information supplementaire pour Etats/ indicateurs
		 */
        if (!empty($custom_entete)) {
            $line = 4;
            $objPHPExcel->getActiveSheet()->getColumnDimension(PHPExcel_Cell::stringFromColumnIndex($line))->setAutoSize(true);
            foreach ($custom_entete as $cle => $valeur) {
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $line, $cle);
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(2, $line, $valeur);
                $line++;
            }
        }

        if (!empty($line_info)) {
            foreach ($line_info as $key => $value) { // tableau à une ligne, c juste pour récupèrer la clé et la valeur
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(1, $startLineHeader - 1, $key);
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(2, $startLineHeader - 1, $value);
            }
        }

        /*
		 * mise en place des en-têtes
		 */
        $key = 0;
        foreach ($array_header as $header) {
            $objPHPExcel->getActiveSheet()->getColumnDimension(PHPExcel_Cell::stringFromColumnIndex($key))->setAutoSize(true);
            //L'entete commence par la ligne 4
            $valueFormated = $header;
            $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($key, $startLineHeader, $valueFormated);
            $key++;
        }

        /*
		 * Insertion du contenu du tableau
		 */
        $row = $startLineBody;
        $type = PHPExcel_Cell_DataType::TYPE_STRING;
        foreach ($contenu as $ligne) {
            $objPHPExcel->getActiveSheet()->setPrintGridlines(TRUE);
            $cpt = 0;
            foreach ($array_header as $key => $header) {
                $valueFormated = isset($ligne[$key]) ? $ligne[$key] : '';
                $objPHPExcel->getActiveSheet()->getCellByColumnAndRow($cpt, $row)->setValueExplicit($valueFormated, $type);
                $cpt++;
            }
            $row++;
        }

        return true;

    }
}

if (!function_exists('do_export_commun')) {
    function do_export_commun($array_header, $titre, $contenu, $nom_fichier, $startLineTitle = 2, $startLineHeader = 4, $startLineBody = 5, $custom_entete = array(), $download = 1, $save_folder = 'downloads/coresp_xls/', $line_info = array())
    {

        $CI =& get_instance();
        $CI->load->library('excel');
        $objPHPExcel = new PHPExcel();

        do_export_commun_feuille($objPHPExcel, $array_header, $titre, $contenu, $startLineTitle, $startLineHeader, $startLineBody, 'Feuille 1', 0, $custom_entete, $line_info);

        // Save it as an excel 2003 file
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

        if ($download == 1) {
            header('Content-type: application/vnd.ms-excel');
            header("Content-Disposition:inline;filename=" . $nom_fichier. ".xls");
            $objWriter->save('php://output');
            return $objPHPExcel;
        } else {
            $objWriter->save($save_folder . $nom_fichier. '.xls');
            return $objPHPExcel;
        }
    }
}


if (!function_exists('is_table_exist')) {
    function is_table_exist($table_name)
    {
        $CI =& get_instance();
        $CI->load->model('reponse/reponse_model');
        return $CI->reponse_model->is_table_exist($table_name);
    }
}

if (!function_exists('is_column_exist_in_table')) {
    function is_column_exist_in_table($table_name, $col_name)
    {
        $CI =& get_instance();
        $CI->load->model('reponse/reponse_model');
        return $CI->reponse_model->is_column_exist_in_table($table_name, $col_name);
    }
}

if (!function_exists('is_column_exist_in_table')) {
    function is_column_exist_in_table($table_name, $col_name)
    {
        $CI =& get_instance();
        $CI->load->model('reponse/reponse_model');
        return $CI->reponse_model->is_column_exist_in_table($table_name, $col_name);
    }
}

if (!function_exists('get_column_type_in_table')) {
    function get_column_type_in_table($table_name, $col_name)
    {
        $CI =& get_instance();
        $CI->load->model('reponse/reponse_model');
        return $CI->reponse_model->get_column_type_in_table($table_name, $col_name);
    }
}

//Nampoina

if (!function_exists('create_entete_dossier')) {
	function create_entete_dossier($ref_dossier,$status,$taux,$id_pret = 0)
	{
		$str ="";
		$resi = "";
		if($id_pret>0){
			$resi = get_resident_by_pret($id_pret);
		}
		if($ref_dossier!=null && $ref_dossier!="")
			$str.='<span id="ref">'.$ref_dossier."</span> -";
		if($status!="" && $status!=null)
			$str.= '<span id="ref_type_pret" class ="status_ref_dossier_text" >'.$status.'</span>';
		if($taux!="" && $taux!=null)
			$str.=" - ".$taux;
		if($resi !=""){
			$str.=' - <span class ="resident">'.$resi.'</span>';
		}
		else{
			$str.='<span class ="resident"></span>';
		}
		
		return $str;
	}
}

if (!function_exists('get_resident_by_pret')) {
	function get_resident_by_pret($id_pret = 0){
		$CI =& get_instance();
		$res_val = "";
		$CI->load->model('demande_cred/formadm_identification_model','emprunteur');
		$crit = array("id_pret"=>$id_pret,
		'empr'=>true);
		$emp = $CI->emprunteur->get_emprunteur_identification($crit);
		if(sizeof($emp)>0){
			$resi = $emp[0]->id_pg_statut_client;
			if($resi == 31){
				
				$res_val = "Non résident";
			}
		}
		return $res_val;
		
	}
}

//CALCUL SCORE

if(function_exists("propose_anciennete_employeur")){
	function propose_anciennete_employeur(){
	
	
	
	}
}
if(!function_exists("get_dossier_client_uploads")){
	function get_dossier_client_uploads($id_pret = 0,$dossier){
		$CI =& get_instance();
		$CI->load->model('demande_cred/pret_model','pret');
		$pret = $CI->pret->get_recuperation_dossier_upload($id_pret);
		$dossier .="/".$pret[0]["radical"]."".$pret[0]["cle"]."/".$pret[0]["ref_dossier"]."/";
		return $dossier;
		
	}
}




if (!function_exists('get_anciennete_employeur')) {
	function get_anciennete_employeur($id_pret,$autre_info,&$score_prop=null,&$prop_duree=null)
	{
		
		
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$info_score = $CI->score->get_info_calcul_score($id_pret);
		$contrat = $info_score[0]->id_pg_nature_contrat;
		$type_empl = $info_score[0]->id_pg_cotation;
		
		
		
		$anc = $info_score[0]->anc;
		
		
		$nb_duree_mois = $autre_info['duree'];
		if($nb_duree_mois>0){
			$info_score[0]->nb_duree_mois = $nb_duree_mois;
		}
		
		if($autre_info['ordre']==1){
		
			$id_score = SCORE_VERT;
			if($info_score[0]->nb_duree_mois==null || $contrat==null || $type_empl==null || $anc==null){
				
				return 0;
			}
			
		}
		else{
			$id_score = $autre_info['id_score'];
			if(($info_score[0]->nb_duree_mois==null || $contrat==null || $type_empl==null || $anc==null) && $nb_duree_mois == 0){
				return $id_score;
			}
		}
		if($nb_duree_mois>0){
		
			$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois);
		}
		
		//pour le fonctionnaire
		$note_employeur = $info_score[0]->li_note_employeur;
		$cotation = $info_score[0]->cotation;
		if($contrat==CDI_CONTRAT){
			switch($type_empl)
			{
				case COTATION_A_FAV:
					$type_empl = "cdi_fonctionnaire,cdi_favoriser";
					$cotation_motif = "Fonctionnaires ou A favoriser";
					break;
				case COTATION_PLUS:
					$type_empl = "cdi_plus";
					$cotation_motif = "A renseigner plus";
					break;
				case COTATION_MOINS:
					$type_empl = "cdi_moins";
					$cotation_motif = "A renseigner moins";
					break;
				case COTATION_CONNU:
					$type_empl = "cdi_connue";
					$cotation_motif = "Connue";
					break;
				default:
					$type_empl = "cdi_moins";
					$cotation_motif = "A renseigner moins";
					break;
			}
			
			
			if(recherche_texte($cotation,"Fonctionnaire" )  || recherche_texte($info_score[0]->fonctionnaire,"Fonctionnaire"))
			{
				$type_empl="cdi_fonctionnaire,cdi_favoriser";
				$cotation_motif ="Fonctionnaires";
			}
			
		}
		else $type_empl = "cdd";
		
		$data = Array('function'=>'get_anciennete_employeur','critere'=>$type_empl);
		
		
		if($prop_duree==null){
			$data['id_mois'] = $id_mois;
		}
		//var_dump($donne_score);
		$donne_score=$CI->score->get_score_by_critere($data);
		
		
		if($prop_duree!=null){
			
			$data = traitement_proposion_score($donne_score,$anc);
			$score_prop[0] =$data['id_score'];
			$prop_duree[0] =intval($data['duree']);
			
			return $data['id_score'];
		}
		$chgt = $donne_score[0]->changement_couleur_si;
		$score_id=$donne_score[0]->id_couleur_score;
		
		
		$insert = 0;
		if($score_id!= SCORE_VERT){
			$id_resultat = $donne_score[0]->id_pret_calcul_scoring;
			if($type_empl== "cdd"){
				$motif = "Le type de contrat pour le score vert doit être CDI";
				$insert = 1;
			}
			else{
				$motif ="Cotation : ".$cotation_motif." -  Durée du prêt : ".$nb_duree_mois." mois";
				$insert = 1;
				
			}
		}
		
		$id_resultat = $donne_score[0]->id_pret_calcul_scoring;
		$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
		$pos = array_search($score_id,$tab);
		
		if($chgt=="<"){
		
			if($donne_score[0]->valeur_min!=null){
				
				if($anc<$donne_score[0]->valeur_min ){
					if($score_id == SCORE_VERT){
						$motif = "L'ancienneté employeur pour le score vert doit être supérieur à ".$donne_score[0]->valeur_min." mois pour le cotation ".$cotation_motif." et Durée : ".$nb_duree_mois." mois";
						$insert = 1;
					}
					
					$score_id =  $tab[$pos+1];
					
					
				}
				
				
			}
		}
		else {
			if($donne_score[0]->valeur_max!=null){
				if($anc>$donne_score[0]->valeur_max){
					if($score_id == SCORE_VERT){
						$motif = "L'ancienneté employeur pour le score vert doit être supérieur à ".$donne_score[0]->valeur_max." mois pour le cotation ".$cotation_motif." et Durée : ".$nb_duree_mois." mois";
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
					
				}
			}
			else if($donne_score[0]->valeur_min!=null){
					
				if($anc>$donne_score[0]->valeur_min){
					if($score_id == SCORE_VERT){
						$motif = "L'ancienneté employeur pour le score vert doit être supérieur à ".$donne_score[0]->valeur_min." mois pour le cotation ".$cotation_motif." et Durée : ".$nb_duree_mois." mois";
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
					
				}
					
			}
		}
		if($score_prop!=null)
		$score_prop[0] = $score_id;
		
		if($insert == 1){
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
			
			$CI->score->insert_resultat_scoring($donnee);
		}
		else{
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
			$CI->score->supprimer_resulat_scoring($donnee);
		}
		
		$pos = array_search($id_score,$tab);
		$pos2 = array_search($score_id,$tab);
		
	
		if($pos2>$pos){
			$id_score = $score_id;
			$pos = $pos2;
		}
		
		return $id_score;
	}
}
if(!function_exists('traitement_proposion_score')){
	function traitement_proposion_score($prop,$rev){
		$temp = array();
	
	
		foreach($prop as $p){
			if($p->valeur_min<=$rev){
				if($p->valeur_max!=null){
					if($rev<=$p->valeur_max){
						$temp[] = $p->id_couleur_score;
					}
				}
				else{
					$temp[] = $p->id_couleur_score;
				}
			}
			
		}
		$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
		foreach($tab as $score){
			$pos = array_search($score,$temp);
			if($pos>=0){
				break;
			}
		}
		
		$data_score = array();
		$data_score['id_score'] = $prop[$pos]->id_couleur_score;
		$data_score['duree'] = $prop[$pos]->mois_max;
		return $data_score;
		
	}
}
if(!function_exists('traitement_proposion_fonctionnement')){
	function traitement_proposion_fonctionnement($prop,$rev){
		$temp = array();
		
		foreach($prop as $p){
			if($p->valeur_min<=$rev){
				if($p->valeur_max!=null){
					if($rev<=$p->valeur_max){
						$temp[] = $p->id_couleur_score;
					}
				}
				else{
					$temp[] = $p->id_couleur_score;
				}
			}
			
		}
		
		$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
		$data_score = array();
		if(sizeof($temp)==0){
			$data_score['id_score'] = 97;
			$data_score['duree']  = 60;
		}
		else{
			foreach($tab as $score){
				$pos = array_search($score,$temp);
				
				if($pos>=0){
					break;
				}
			}
			
			
			$data_score['id_score'] = $prop[$pos]->id_couleur_score;
			$data_score['duree'] = $prop[$pos]->mois_max;
		}
		return $data_score;
		
	}
}
if(!function_exists('traitement_taux_charge')){
	function traitement_taux_charge($prop,$rev){
		$temp = array();
		
		foreach($prop as $p){
			if($p->valeur_min<=$rev){
				if($p->valeur_max!=null){
					if($rev<=$p->valeur_max){
						$temp[] = $p->id_couleur_score;
					}
				}
				else{
					$temp[] = $p->id_couleur_score;
				}
			}
			
		}
		$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
		$data_score = array();
		if(sizeof($temp)==0){
			$data_score['id_score'] = 97;
			$data_score['duree']  = 60;
		}
		else{
			foreach($tab as $score){
				$pos = array_search($score,$temp);
				if($pos>=0){
					break;
				}
			}
			
			
			$data_score['id_score'] = $prop[$pos]->id_couleur_score;
			$data_score['duree'] = $prop[$pos]->mois_max;
		}
		return $data_score;
		
	}
}
if (!function_exists('get_revenu_stable')) {
	function get_revenu_stable($id_pret,$autre_info,&$score_prop=null, &$prop_duree = null){
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$info_score = $CI->score->get_info_calcul_score($id_pret);
		$type_empl = $info_score[0]->id_pg_cotation;
		$revenus =  $info_score[0]->mt_salaire_net;
		
		//var_dump($revenus);
		
		$nb_duree_mois=$autre_info['duree'];
		if($nb_duree_mois== 0){
			$nb_duree_mois = $info_score[0]->nb_duree_mois==null ? 0 : $info_score[0]->nb_duree_mois;
		}
	
		$note_employeur = $info_score[0]->li_note_employeur;
	
		if($autre_info['ordre']==1){
			$id_score = SCORE_VERT;
			if($info_score[0]->nb_duree_mois==null || $revenus==null || $revenus==0 || $type_empl==null){
				return 0;
			}
		}
		else{
			if($prop_duree!=null){
				$info_score[0]->nb_duree_mois = $nb_duree_mois;
			}
			$id_score = $autre_info['id_score'];
			if(($info_score[0]->nb_duree_mois==null || $revenus==null ||  $revenus==0  || $type_empl==null) && $nb_duree_mois == 0){
				
				return $id_score;
			}
		}
		
		$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois);
		//pour le fonctionnaire
			switch($type_empl)
			{
				case COTATION_A_FAV:
					$type_empl = "cdi_fonctionnaire,cdi_favoriser";
					$cotation_motif = "Fonctionnaire ou A Favoriser";
					
					break;
				case COTATION_PLUS:
					$type_empl = "cdi_plus";
					$cotation_motif = "A renseigner plus";
					break;
				case COTATION_MOINS:
					$type_empl = "cdi_moins";
					$cotation_motif = "A renseigner moins";
					break;
				case COTATION_CONNU:
					$type_empl = "cdi_connue";
					$cotation_motif = "Connue";
					break;
				default:
					$type_empl = "cdi_moins";
					$cotation_motif = "A renseigner moins";
					break;
			}
			$cotation = $info_score[0]->cotation;
			if(recherche_texte($cotation,"Fonctionnaire" )  || recherche_texte($info_score[0]->fonctionnaire,"Fonctionnaire"))
			{
				$type_empl="cdi_fonctionnaire,cdi_favoriser";
				$cotation_motif ="Fonctionnaire";
			}
			
	
		$data = Array('function'=>'get_revenu_stable','critere'=>$type_empl);
		if($prop_duree!=null){
			//$data['anceinite']= $revenus;
		}
		else{
			$data['id_mois'] = $id_mois;
		}
		$donne_score=$CI->score->get_score_by_critere($data);
		
		if($prop_duree!=null){
			$data = traitement_proposion_score($donne_score,$revenus);
			
			$score_prop[0] =$data['id_score'];
			$prop_duree[0] =intval($data['duree']);
			return $data['id_score'];
		}
		$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
	
		$insert = 0;
		if(count($donne_score)>1){
			for($i=0;$i<count($donne_score);$i++){
				if($donne_score[$i]->valeur_max!=null && $donne_score[$i]->valeur_min!=null){
					if ($revenus>=$donne_score[$i]->valeur_min && $revenus<$donne_score[$i]->valeur_max){
						$score_id=$donne_score[$i]->id_couleur_score;
						if($score_id!=SCORE_VERT){
							$motif = "Revenus stable compris entre ".$donne_score[$i]->valeur_min." MGA et ".$donne_score[$i]->valeur_max." MGA - Cotation : ".$cotation_motif." Durée : ".$nb_duree_mois." mois";
							
					
							$insert = 1;
						}
						
						$id_resultat = $donne_score[$i]->id_pret_calcul_scoring;
						break;
					}
				}
				else{
					if($donne_score[$i]->valeur_max!=null){
						if ($revenus<$donne_score[$i]->valeur_max){
							$score_id=$donne_score[$i]->id_couleur_score; 
							$id_resultat = $donne_score[$i]->id_pret_calcul_scoring;
							if($score_id!=SCORE_VERT){
								$motif = "Revenus stable inférieur à ".$donne_score[$i]->valeur_max." MGA  - Cotation : ".$cotation_motif." Durée : ".$nb_duree_mois." mois";
								$insert = 1;
							}
							break;
						}
					}
					if($donne_score[$i]->valeur_min!=null){
						if ($revenus>=$donne_score[$i]->valeur_min){	
							$score_id=$donne_score[$i]->id_couleur_score;
							$id_resultat = $donne_score[$i]->id_pret_calcul_scoring;
							if($score_id!=SCORE_VERT){
								$motif = "Revenus stable supérieur ou égale à  ".$donne_score[$i]->valeur_min." MGA  - Cotation : ".$cotation_motif." Durée : ".$nb_duree_mois." mois";
								$insert = 1;
							}
							break;
						}
					}
				}
			}
			$score_id_temp = $score_id;
			$pos = array_search($id_score,$tab);
			$pos2 = array_search($score_id,$tab);
			/*if($id_score==$score_id){
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
				$CI->score->insert_resultat_scoring($donnee);
			}*/
			if($pos2>$pos){
				$id_score = $score_id;
				$pos = $pos2;
			}
			
			if($score_prop!=null)
				$score_prop[0] = $score_id;
			if($insert == 1){
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
				$CI->score->insert_resultat_scoring($donnee);
			}
			else{
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
				$CI->score->supprimer_resulat_scoring($donnee);
			}
			
		}
		else{
			if($prop_duree!=null){
				$prop_duree[0] =  $donne_score[0]->mois_max;
			}
			$chgt = $donne_score[0]->changement_couleur_si;
			$score_id=$donne_score[0]->id_couleur_score;
			$id_resultat = $donne_score[0]->id_pret_calcul_scoring;
			if($score_id!=SCORE_VERT){
				$insert = 1;
				if($insert == 1){
					$motif = "Revenus stables : ".$revenus." ,Durée prêt : ".$nb_duree_mois." et cotation : ".$cotation_motif;
					$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
					$CI->score->insert_resultat_scoring($donnee);
				}
				else{
					$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
					$CI->score->supprimer_resulat_scoring($donnee);
				}
			}
			
			
			$pos = array_search($score_id,$tab);
			if($donne_score[0]->valeur_min!=null || $donne_score[0]->valeur_max!=null){
				if($chgt=="<"){
					if($donne_score[0]->valeur_min!=null){
						if($revenus<$donne_score[0]->valeur_min){
							if($score_id ==SCORE_VERT){
								$motif = "Le revenus stables pour le score vert doit être supérieur à  ".$donne_score[0]->valeur_min." MGA  pour le  Cotation : ".$cotation_motif." et Durée du prêt : ".$nb_duree_mois." mois";
								$insert = 1;
							}
							else{
								$motif = "Revenus stable inférieur à   ".$donne_score[0]->valeur_min." MGA  - Cotation : ".$cotation_motif." Durée : ".$nb_duree_mois." mois";
								$insert = 1;
							}
							$score_id =  $tab[$pos+1];
							
							
						}
					}
				}
				else {
					if($donne_score[0]->valeur_max!=null){
						if($revenus>$donne_score[0]->valeur_max){
							$score_id =  $tab[$pos+1];
						}
					}
					else if($donne_score[0]->valeur_min!=null){	
						if($revenus>$donne_score[0]->valeur_min){
							$score_id =  $tab[$pos+1];
						}
							
					}
				}
			}
			if($score_prop!=null)
				$score_prop[0] = $score_id;
		
			if($insert == 1){
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
				$CI->score->insert_resultat_scoring($donnee);
			}
			else{
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
				$CI->score->supprimer_resulat_scoring($donnee);
			}
			$pos = array_search($id_score,$tab);
			$pos2 = array_search($score_id,$tab);
			if($pos2>$pos){
				$id_score = $score_id;
				$pos = $pos2;
			}
		}
		return $id_score;
	}
}
if (!function_exists('get_autres_revenus')) {
	function get_autres_revenus($id_pret,$autre_info,&$score_prop=null,&$prop_duree=null){
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$info_score = $CI->score->get_info_calcul_score($id_pret);
		$nb_duree_mois = $autre_info['duree'];
		$revenus =  $info_score[0]->mt_salaire_net;
		$autres_revenus = $info_score[0]->mt_autres_revenus_net;
		if($nb_duree_mois == 0){
			$nb_duree_mois = ($info_score[0]->nb_duree_mois==null )? 0 : $info_score[0]->nb_duree_mois;
		}
		if($autre_info['ordre']==1){
			$id_score = SCORE_VERT;
			if($info_score[0]->nb_duree_mois==null || $revenus==0 || $revenus==null || $autres_revenus==null){
				return 0;
			}
		}
		else{
			$id_score = $autre_info['id_score'];
			if(($info_score[0]->nb_duree_mois==null ||$revenus==0 ||  $revenus==null || $autres_revenus==null || $autres_revenus==0) && $nb_duree_mois == 0){
				return $id_score;
			}
		}
		$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois);
		$percent =0;
		if($revenus>0){
			$percent = ($autres_revenus*100/$revenus);
		}
		$data = Array('function'=>'get_autres_revenus','critere'=>'moyenne_6_derniers_mois');
		if($prop_duree==null){
			$data['id_mois']=$id_mois;
		}
		$donne_score=$CI->score->get_score_by_critere($data);
		if($prop_duree!=null){
		
			$data = traitement_proposion_score($donne_score,$percent);
			$score_prop[0] =$data['id_score'];
			$prop_duree[0] = intval($data['duree']);
			return $data['id_score'];
			
		}
		
		$chgt = $donne_score[0]->changement_couleur_si;
		$score_id=$donne_score[0]->id_couleur_score;
		
		$insert = 0;
		if($score_id!=SCORE_VERT){
			$motif ="Durée du prêt : ".$nb_duree_mois." mois";
			$insert = 1;
		}
		$id_resultat = $donne_score[0]->id_pret_calcul_scoring;
		$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
		$pos = array_search($score_id,$tab);
		
		if($chgt=="<"){
			if($donne_score[0]->valeur_min!=null){
				if($percent<$donne_score[0]->valeur_min){
					if($score_id == SCORE_VERT){
						$motif = " < 35% ";
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
				}
			}
		}
		else {
			if($donne_score[0]->valeur_max!=null){
				if($percent>$donne_score[0]->valeur_max){
					
					if($score_id == SCORE_VERT){
						$motif = " Le pourcentage dans revenu stable pour un score vert doit être inferieur ou égale à  35% ";
						$insert = 1;
					}
					//$motif = " > 35 % ";
					//$insert = 1;
					$score_id =  $tab[$pos+1];
				}
			}
			else if($donne_score[0]->valeur_min!=null){
					
				if($percent>$donne_score[0]->valeur_min){
					if($score_id == SCORE_VERT){
						$motif = " Le pourcentage dans revenu stable pour un score vert doit être inferieur ou égale à  35% ";
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
				}
					
			}
		}
		
		if($score_prop!=null)
			$score_prop[0] = $score_id;
		if($insert== 1){
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
			$CI->score->insert_resultat_scoring($donnee);
		}
		else{
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
			$CI->score->supprimer_resulat_scoring($donnee);
		}
		/*if($id_score==$score_id){
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
			$CI->score->insert_resultat_scoring($donnee);
		}*/
		$pos = array_search($id_score,$tab);
		$pos2 = array_search($score_id,$tab);
		if($pos2>$pos){
			$id_score = $score_id;
			$pos = $pos2;
		}
		return $id_score;
	}
}
if (!function_exists('get_age')) {
	function get_age($id_pret,$autre_info,&$score_prop=null,&$prop_duree=null)
	{
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$info_score = $CI->score->get_info_calcul_score($id_pret);
		
		
		$nb_duree_mois = $autre_info['duree'];
		
		if(!empty($info_score[0]->nb_duree_mois) && isset($info_score[0]->nb_duree_mois)){
			
			$nb_duree_mois = $info_score[0]->nb_duree_mois;
		}
		
		$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois );
		//pour le fonctionnaire
		$age = $info_score[0]->age;
		$note_employeur = $info_score[0]->li_note_employeur;
		
		if($autre_info['ordre']==1){
			$id_score = SCORE_VERT;
			if($info_score[0]->nb_duree_mois==null && $nb_duree_mois==0 ){
				return 0;
			}
		}
		else{
			$id_score = $autre_info['id_score'];
			if($info_score[0]->nb_duree_mois==null && $nb_duree_mois==0 ){
				
				return $id_score;
			}
		}
		
		$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois);
		if(recherche_texte($note_employeur,"Fonctionnaire"))
		{
			$type_empl="Fonctionnaire";
		}
		else $type_empl="autre_employeur";
		$data = Array('function'=>'get_age','critere'=>$type_empl);
		if($prop_duree==null){
			$data['id_mois'] = $id_mois;
		}
		$donne_score=$CI->score->get_score_by_critere($data);
		if($prop_duree!=null){
			$data = traitement_proposion_score($donne_score,$age);
			
			
			$score_prop[0] =$data['id_score'];
			$prop_duree[0] =intval($data['duree']);
			return $data['id_score'];
		}
		$chgt = $donne_score[0]->changement_couleur_si;
		$score_id=$donne_score[0]->id_couleur_score;
		$insert = 0;
		if($score_id!=SCORE_VERT){
			$motif ="Durée du prêt : ".$nb_duree_mois." mois";
			$insert = 1;
		}
		$id_resultat = $donne_score[0]->id_pret_calcul_scoring;
		$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
		$pos = array_search($score_id,$tab);
		if($chgt=="<"){
			if($donne_score[0]->valeur_min!=null){
				if($age<$donne_score[0]->valeur_min){
					if($score_id == SCORE_VERT){
						$motif ="L'âge pour le score vert doit être supérieur à ".$donne_score[0]->valeur_min."
						pour le durée du prêt ".$nb_duree_mois." mois";
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
				}
			}
		}
		else {
			if($donne_score[0]->valeur_max!=null){
				if($age>$donne_score[0]->valeur_max){
					if($score_id == SCORE_VERT){
						$motif ="L'âge pour le score vert doit être inférieur à ".$donne_score[0]->valeur_max."
						pour le durée du prêt ".$nb_duree_mois." mois";
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
				}
			}
			else if($donne_score[0]->valeur_min!=null){
					
				if($age>$donne_score[0]->valeur_min){
					$score_id =  $tab[$pos+1];
				}
					
			}
		}
		if($score_prop!=null)
			$score_prop[0] = $score_id;
		if($insert == 1){
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
			$CI->score->insert_resultat_scoring($donnee);
		}
		else{
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
			$CI->score->supprimer_resulat_scoring($donnee);
		}
		/*if($id_score==$score_id){
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
			$CI->score->insert_resultat_scoring($donnee);
		}*/
		$pos = array_search($id_score,$tab);
		$pos2 = array_search($score_id,$tab);
		if($pos2>$pos){
			$id_score = $score_id;
			$pos = $pos2;
		}
		return $id_score;
	}
}
if (!function_exists('get_montant_credit')) {
	function get_montant_credit($id_pret,$autre_info,&$score_prop=null,$montant=0)
	{
	
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$info_score = $CI->score->get_info_calcul_score($id_pret);
		$nb_duree_mois = $autre_info['duree'];
		if($nb_duree_mois == 0){
			$nb_duree_mois  = ($info_score[0]->nb_duree_mois==null) ? 0 : $info_score[0]->nb_duree_mois;
		}
		$mont_credit = 0;
	
		if(!empty($autre_info['montant'])){
			if($autre_info['montant']>0){
				$mont_credit= $autre_info['montant'];
				
			}
		}
		if($mont_credit==0){
			$mont_credit = $info_score[0]->mt_capital_emprunte;
			
		}
		
		if($autre_info['ordre']==1){
			$id_score = SCORE_VERT;
			if($info_score[0]->nb_duree_mois==null || $mont_credit==null){
				return 0;
			}
		}
		else{
			$id_score = $autre_info['id_score'];
			
			if($nb_duree_mois==0 && $mont_credit==0){
				if($info_score[0]->nb_duree_mois==null || $mont_credit==null){
					return $id_score;
				}
			}
		}
		
		$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois);
		$data = Array('function'=>'get_montant_credit','critere'=>"",'id_mois'=>$id_mois);
		$donne_score=$CI->score->get_score_by_critere($data);
		$insert = 0;
		
		if(count($donne_score)>1){
			
			for($i=0;$i<count($donne_score);$i++){
			
				if($donne_score[$i]->valeur_max!=null && $donne_score[$i]->valeur_min!=null){
			
					if ($mont_credit>=$donne_score[$i]->valeur_min && $mont_credit<=$donne_score[$i]->valeur_max){	
								
						$score_id=$donne_score[$i]->id_couleur_score;
						if($score_id!=SCORE_VERT){
							if($score_id == SCORE_ORANGE){
								$insert = 1;
								$motif = "Le montant crédit pour un score vert doit être inférieur à ".$donne_score[$i]->valeur_min." MGA";
							}
							else{
								$motif = "Le montant crédit pour un score vert doit être inférieur à 8000000 MGA";
								$insert = 1;
							}
						}
						$id_resultat = $donne_score[$i]->id_pret_calcul_scoring;
						break;
					}
				}
				else{
					
					if($donne_score[$i]->valeur_max!=null){
						if ($mont_credit<=$donne_score[$i]->valeur_max){
							$score_id=$donne_score[$i]->id_couleur_score;
							if($score_id!=SCORE_VERT){
								if($score_id == SCORE_ORANGE){
									$insert = 1;
									$motif = "Durée du prét : ".$nb_duree_mois.' mois';
								}
								else{
									$motif = "Le montant crédit pour un score vert doit être inférieur à 8000000 MGA";
									$insert = 1;
								}
							}
							$id_resultat = $donne_score[$i]->id_pret_calcul_scoring;
							break;
						}
					}
					if($donne_score[$i]->valeur_min!=null){
						
						if ($mont_credit>=$donne_score[$i]->valeur_min){
							
							$score_id=$donne_score[$i]->id_couleur_score;
							if($score_id!=SCORE_VERT){
								$motif = "Le montant crédit pour un score vert doit être inférieur à 8000000 MGA";
								$insert = 1;
							}
							$id_resultat = $donne_score[$i]->id_pret_calcul_scoring;
							break;
						}
					}
				}
				
			}
		    }
			
		    if($score_prop!=null)
		    	$score_prop[0] = $score_id;
			$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
			
			if($insert== 1){
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
				$CI->score->insert_resultat_scoring($donnee);
			}
			else{
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
				$CI->score->supprimer_resulat_scoring($donnee);
			}
			/*if($id_score==$score_id){
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
				$CI->score->insert_resultat_scoring($donnee);
			}*/
			$pos = array_search($id_score,$tab);
			$pos2 = array_search($score_id,$tab);
			if($pos2>$pos){
				$id_score = $score_id;
				$pos = $pos2;
			}
			
			return $id_score;
	}
}
if (!function_exists('get_anciennete_bni')) {
	function get_anciennete_bni($id_pret,$autre_info,&$score_prop=null,&$prop_duree=null)
	{
		
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$info_score = $CI->score->get_info_calcul_score($id_pret);

		$type_empl = $info_score[0]->id_pg_cotation;
		$anc = $info_score[0]->anc_bni;
	
		$nb_duree_mois = $autre_info['duree'];
		if($nb_duree_mois == 0){
			$nb_duree_mois  = ($info_score[0]->nb_duree_mois==null) ? 0: $info_score[0]->nb_duree_mois; 
		}
		
		if($autre_info['ordre']==1){
			$id_score = SCORE_VERT;
			if($info_score[0]->nb_duree_mois==null ||  $type_empl==null || $anc==null){
				return 0;
			}
		}
		else{
			$id_score = $autre_info['id_score'];
		
			if(($info_score[0]->nb_duree_mois==null ||  $type_empl==null || $anc==null) && $nb_duree_mois==0 ){
				return $id_score;
			}
		}
		if($anc == null){
			
			$id_score = $autre_info['id_score'];
			return $id_score;
		}
		$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois);
		//pour le fonctionnaire
		$note_employeur = $info_score[0]->li_note_employeur;
		
		switch($type_empl)
		{
			case COTATION_A_FAV:
				$type_empl = "fonctionnaire,favoriser";
				$motif_cotation = "Fonctionnaire ou A favoriser";
				break;
			case COTATION_PLUS:
				$type_empl = "plus";
				$motif_cotation = "A renseinger plus";
				break;
			case COTATION_MOINS:
				$type_empl = "moins";
				$motif_cotation = "A renseinger moins";
				break;
			case COTATION_CONNU:
				$type_empl = "connue";
				$motif_cotation = "Connue";
				break;
			default:
				$type_empl = "moins";
					$motif_cotation = "A renseinger moins";
				break;
		}
		$cotation = $info_score[0]->cotation;
			if(recherche_texte($cotation,"Fonctionnaire" )  || recherche_texte($info_score[0]->fonctionnaire,"Fonctionnaire"))
			{
				$type_empl="fonctionnaire,favoriser";
				$motif_cotation = "Fonctionnaire";
			}
		$data = Array('function'=>'get_anciennete_bni','critere'=>$type_empl);
		if($prop_duree==null){
			$data['id_mois'] = $id_mois;
		}
		
		$donne_score=$CI->score->get_score_by_critere($data);
		
		if($prop_duree!=null){
			$data = traitement_proposion_score($donne_score,$anc);
			
			$score_prop[0] =$data['id_score'];
			$prop_duree[0] =intval($data['duree']);
			return $data['id_score'];
		}
		
		
		$chgt = $donne_score[0]->changement_couleur_si;
		$insert = 0;
		$score_id=$donne_score[0]->id_couleur_score;
		if($score_id!=SCORE_VERT){
			$motif = "Cotation : ".$motif_cotation." - Durée de pret : ".$nb_duree_mois. "mois";
			$insert = 1;
		}
		
		$id_resultat = $donne_score[0]->id_pret_calcul_scoring;
		$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
		if($autre_info['ordre']==1)
			$id_score = SCORE_VERT;
		else $id_score = $autre_info['id_score'];
		$pos = array_search($score_id,$tab);
		
		
		if($chgt=="<"){
			if($donne_score[0]->valeur_min!=null){
			
				if($anc<$donne_score[0]->valeur_min){
					if($score_id== SCORE_VERT){
						$motif = " L'enceinnete BNI pour le score vert doit être superieur ou égale à " .$donne_score[0]->valeur_min." mois pour le Cotation : ".$motif_cotation." et  Durée de pret : ".$nb_duree_mois. "mois";
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
					
					
				}
			}
			
		}
		else {
			if($donne_score[0]->valeur_max!=null){
				if($anc>$donne_score[0]->valeur_max){
					if($score_id== SCORE_VERT){
						$motif = " L'enceinnete BNI pour le score vert doit être inférieur ou égale à " .$donne_score[0]->valeur_max." mois pour le Cotation : ".$motif_cotation." et  Durée de pret : ".$nb_duree_mois. "mois";
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
					
				}
			}
			else if($donne_score[0]->valeur_min!=null){
					
				if($anc>$donne_score[0]->valeur_min){
					if($score_id== SCORE_VERT){
						$motif = " L'enceinnete BNI pour le score vert doit être inférieur ou égale à " .$donne_score[0]->valeur_min." mois pour le Cotation : ".$motif_cotation." et  Durée de pret : ".$nb_duree_mois. "mois";
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
					
				}
					
			}
		}
		if($score_prop!=null)
			$score_prop[0] = $score_id;
		if($insert == 1){
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
			$CI->score->insert_resultat_scoring($donnee);
		}
		else{
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
			$CI->score->supprimer_resulat_scoring($donnee);
		}
		/*if($id_score==$score_id){
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
			$CI->score->insert_resultat_scoring($donnee);
		}*/
		$pos = array_search($id_score,$tab);
		$pos2 = array_search($score_id,$tab);
		if($pos2>$pos){
			$id_score = $score_id;
		}
		
			return $id_score;
	}
}
if (!function_exists('get_duree')){
	function get_duree($id_pret,$autre_info,&$score_prop=null,&$prop_duree =null)
	{
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$info_score = $CI->score->get_info_calcul_score($id_pret);
		$is_differe = $info_score[0]->is_differe_2_mois_capital;
		
		$duree = $info_score[0]->nb_mois_differe;
		$nb_duree_mois = $autre_info['duree'];
		
		if($autre_info['ordre']==1){
			$id_score = SCORE_VERT;
			if($is_differe==0 || $is_differe==null){
				return 0;
			}
		}
		else{
			$id_score = $autre_info['id_score'];
			if($is_differe==0 || $is_differe==null){
				return $id_score;
			}
		}
		
		$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois);
		$data = Array('function'=>'get_duree','critere'=>"anticipe");
		if($prop_duree==null){
			$data['id_mois']= $id_mois;
		}
		$donne_score=$CI->score->get_score_by_critere($data);
		if($prop_duree!=null){
			$data = traitement_proposion_score($donne_score,$duree);
			$score_prop[0] =$data['id_score'];
			$prop_duree[0] =intval($data['duree']);
			return $data['id_score'];
		}
		
		$chgt = $donne_score[0]->changement_couleur_si;
		$id_resultat = $donne_score[0]->id_pret_calcul_scoring;
		$score_id=$donne_score[0]->id_couleur_score;
		$insert = 0;
		if($score_id!=SCORE_VERT){
			$motif  = "Durée de pret : ".$nb_duree_mois.' mois';
			$insert = 1;
			
		}
		$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
		$pos = array_search($score_id,$tab);
		if($chgt=="<"){
			if($donne_score[0]->valeur_min!=null){
				if($duree<$donne_score[0]->valeur_min){
					if($score_id == SCORE_VERT){
						$motif  = "Durée du CAP en cours inferieur à ".$donne_score[0]->valeur_min.' mois';
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
				}
			}
		}
		else {
			if($donne_score[0]->valeur_max!=null){
				if($duree>$donne_score[0]->valeur_max){
					if($score_id == SCORE_VERT){
						$motif  = "Durée du CAP en cours supérieur  à ".$donne_score[0]->valeur_max.' mois';
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
				}
			}
			else if($donne_score[0]->valeur_min!=null){
		
				if($duree>$donne_score[0]->valeur_min){
					if($score_id == SCORE_VERT){
						$motif  = "Durée du CAP en cours supérieur  à ".$donne_score[0]->valeur_min.' mois';
						$insert = 1;
					}
					$score_id =  $tab[$pos+1];
					
				}
		
			}
		}
		
		if($score_prop!=null)
			$score_prop[0] = $score_id;
		if($insert== 1 ){
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
			$CI->score->insert_resultat_scoring($donnee);
		}
		else{
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
			$CI->score->supprimer_resulat_scoring($donnee);
		}
		/*if($score_id==$id_score){
			$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
			$CI->score->insert_resultat_scoring($donnee);
		}*/
		$pos = array_search($id_score,$tab);
		$pos2 = array_search($score_id,$tab);
		if($pos2>$pos){
			$id_score = $score_id;
		}
		return $id_score;
	}
}
if (!function_exists('get_taux_charge')){
	function get_taux_charge($id_pret,$autre_info,&$score_prop=null,&$prop_duree=null)
	{
		
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$nb_duree_mois = $autre_info['duree'];
		
		if(!empty($autre_info['via_produit'])){
			$via_produit = $autre_info['via_produit'];
		}
		else{
			$via_produit = false;
		}
		
		
		if(empty($autre_info['taux_charge'])){
			$taux_emp = (19+0.8)/100;
		}
		else{
			$taux_emp = (19+0.8)/100;
			if($autre_info['taux_charge']>0){
				$taux_emp = $autre_info['taux_charge'];
			}
		}
		if($autre_info['ordre']==1){
			$id_score = SCORE_VERT;
			if($nb_duree_mois == null){
				return 0;
			}
		}
		else{
			$id_score = $autre_info['id_score'];
			if($nb_duree_mois == null){
				return $id_score;
			}
		}
		
		$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois);
		
		$info_taux = $CI->score->get_info_taux_de_charges($id_pret);
		$peut = 0;
		if(count($info_taux)>0){
			$peut = 1;
			$charge = 0;
			$co_charge = 0;
			$autre_rev = 0;
			$co_autre_rev = 0;
			$sal_emp = 0;
			$sal_co_emp = 0;
			$taux = 0;
			$echeance = 0;
			if($info_taux[0]->echeance!=null)
			$echeance = $info_taux[0]->echeance;
			if($info_taux[0]->charge!=null)
			$charge = $info_taux[0]->charge;
			if($info_taux[0]->co_charge!=null)
			$co_charge = $info_taux[0]->co_charge;
			if($info_taux[0]->mt_autres_revenus_net!=null)
			$autre_rev = $info_taux[0]->mt_autres_revenus_net;
			if($info_taux[0]->co_autre_revenus!=null)
			$co_autre_rev = $info_taux[0]->co_autre_revenus;
			$t_max = 0.33; 
			if($info_taux[0]->mt_salaire_net!=null)
			$sal_emp = $info_taux[0]->mt_salaire_net;
			if($info_taux[0]->co_mt_salaire_net!=null)
			$sal_co_emp = $info_taux[0]->co_mt_salaire_net;
			$tot_charge_menage = $charge + $co_charge;
			if(35*$sal_emp/100<=$autre_rev)
			$qc_autre_rev = 35*$sal_emp/100;
			else $qc_autre_rev = $autre_rev;
			if(35*$sal_co_emp/100<=$co_autre_rev)
				$qc_co_autre_rev = 35*$sal_co_emp/100;
			else $qc_co_autre_rev = $co_autre_rev;
			$qc_menage = ($sal_emp+$sal_co_emp+$qc_autre_rev+$qc_co_autre_rev)/3;
			$as = 0;
			$taux_annuel  = 0;
			if(!empty($info_taux[0]->assurance)){
				$assurance = explode("%",$info_taux[0]->assurance);
				$as = $assurance[0];
			}
			
			if(!empty($info_taux[0]->taux_annuel)){
				$taux_annuel = $info_taux[0]->taux_annuel;
			}
			$taux = ($as + $taux_annuel)/100;
			$mens_all = calcul_mensualite_pret($info_taux[0]->salaire_net, $info_taux[0]->duree_pret, $taux);
			if($autre_info['ordre']==1){
				if($qc_menage==0){
					return 0;
				}
			}
			else{
			if($qc_menage==0){
					return $id_score;
				}
			}
			
			$taux = (($mens_all + $tot_charge_menage)*($t_max))/$qc_menage;
			
		
			$taux= $taux*100;
		}
		
		if($via_produit){
			
			$taux = $autre_info['taux_charge'];
			$peut = 1;
		}
		if($peut){
			
			$data = Array('function'=>'get_taux_charge','critere'=>"");
			if($prop_duree==null){
				$data['id_mois'] = $id_mois;
			}
			else{
				$data['id_mois'] = $id_mois;
			}
			
			$donne_score=$CI->score->get_score_by_critere($data);
			
			if($prop_duree!=null){
				
				$data = traitement_taux_charge($donne_score,$taux);
				$score_prop[0] =$data['id_score'];
				$prop_duree[0] =intval($data['duree']);
				
				return $data['id_score'];
			}
	
			
			$score_id=SCORE_VERT;
			
			//var_dump($donne_score);
			//var_dump($data);
			if(count($donne_score)>0){
				$temp_id = 0;
				foreach($donne_score as $info){
					
					if($taux>33 ){
						
						if($info->valeur_max==40){
							$score_id = $info->id_couleur_score;
							$temp_id = 1;
							$chgt = $info->changement_couleur_si;
							$id_resultat = $info->id_pret_calcul_scoring;
							$valmax = $info->valeur_max;
							$valmin = $info->valeur_min;
							break;
						}
						if($info->valeur_max==200 && $taux<=200){
							$score_id = $info->id_couleur_score;
							$temp_id = 1;
							$chgt = $info->changement_couleur_si;
							$id_resultat = $info->id_pret_calcul_scoring;
							$valmax = $info->valeur_max;
							$valmin = $info->valeur_min;
							break;
						}
					}
					else{
						
						if($info->valeur_max==33 && $taux<=33){
							$score_id = $info->id_couleur_score;
							$chgt = $info->changement_couleur_si;
							$id_resultat = $info->id_pret_calcul_scoring;
							$valmax = $info->valeur_max;
							$valmin = $info->valeur_min;
							$temp_id = 1;
							break;
						}
					}
				}
				
				$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
				$pos = array_search($score_id,$tab);
				
				if(!empty($chgt)){
					
					if($chgt==">"){
						if($taux>$valmax){
							$score_id = $tab[$pos+1];
						}
					}
					else{
						if($taux<$valmin){
							$score_id = $tab[$pos+1];
						}
					}
				}
				if($score_prop!=null)
					$score_prop[0] = $score_id;
				if($score_id!=SCORE_VERT){
					$motif = "Le taux de charge pour un score vert doit être inférieur ou egale à 33 %";
					$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
					$CI->score->insert_resultat_scoring($donnee);
				}
				else{
					$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
					$CI->score->supprimer_resulat_scoring($donnee);
				}
				/*if($id_score==$score_id){
					$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
					$CI->score->insert_resultat_scoring($donnee);
				}*/
				$pos = array_search($id_score,$tab);
				$pos2 = array_search($score_id,$tab);
				if($pos2>$pos){
					$id_score = $score_id;
					$pos = $pos2;
				}
			}
			
		}
		
		return $id_score;
	}
}

// get_nombre_impayées //
if(!function_exists('get_nombre_impaye_contrat_et_cheque')){
	function get_nombre_impaye_contrat_et_cheque($id_client = 0){
		$CI =& get_instance();
		$CI->load->model('isba/isba_model','isba');
		$criteres = array("id_client"=>$id_client);
		$nb_contrat = $CI->isba->get_nombre_impayes_contrat($criteres );
		$nb_cheque = $CI->isba->get_nombre_impayes_cheque($criteres);
		$nb_total_fonctionnement = $nb_contrat + $nb_cheque ;
		return $nb_total_fonctionnement;
	}
}
if (!function_exists('get_fonctionnement_compte')){
	function get_fonctionnement_compte($id_pret,$autre_info,&$score_prop=null,&$prop_duree=null)
	{
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$nb_duree_mois = $autre_info['duree'];
		$is_client = $CI->score->test_if_client_bni($id_pret);
		$info_score = $CI->score->get_info_calcul_score($id_pret);
		//$id_mois = $CI->score->get_id_mois_calcul_score($info_score[0]->nb_duree_mois);
		$insert = 0;
		if($autre_info['ordre']==1){
			$id_score = SCORE_VERT;
			if($nb_duree_mois == null || !$is_client){
				return 0;
			}
		}
		else{
			$id_score = $autre_info['id_score'];
			if($nb_duree_mois == null || !$is_client){
				return $id_score;
			}
		}
			$id_mois = $CI->score->get_id_mois_calcul_score($nb_duree_mois);
			$impayes = $CI->score->get_impayes($id_pret);
			$data = Array('function'=>'get_fonctionnement_compte','critere'=>"impayes");
			if($prop_duree==null){
				$data['id_mois']= $id_mois;
			}
			$donne_score=$CI->score->get_score_by_critere($data);
			if($prop_duree!=null){
				
				$data = traitement_proposion_fonctionnement($donne_score,$impayes);
				
				$score_prop[0] =$data['id_score'];
				$prop_duree[0] =intval($data['duree']);
				return $data['id_score'];
			}
		
			for($i=0;$i<count($donne_score);$i++){
				if($impayes>=2){
					if($donne_score[$i]->valeur_max==2){
						$chgt = $donne_score[$i]->changement_couleur_si;
						$score_id=$donne_score[$i]->id_couleur_score;
						$valmax = $donne_score[$i]->valeur_max;
						$valmin = $donne_score[$i]->valeur_min;
						$id_resultat = $donne_score[$i]->id_pret_calcul_scoring;
					}
				}
				else{
					if($donne_score[$i]->valeur_max==$impayes){
						
						$chgt = $donne_score[$i]->changement_couleur_si;
						$valmax = $donne_score[$i]->valeur_max;
						$valmin = $donne_score[$i]->valeur_min;
						$score_id=$donne_score[$i]->id_couleur_score;
						$id_resultat = $donne_score[$i]->id_pret_calcul_scoring;
					}
				}
			}
			if($score_id!=SCORE_VERT){
				$insert = 1;
				$motif = " > 0";
			}
			$tab = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_IRREVERSIBLE);
			$pos = array_search($score_id,$tab);
			if($chgt=="<"){
				if($valmin!=null){
					if($impayes<$valmin){
						if($score_id==SCORE_VERT){
							$insert = 1;
							$motif = " <  ".$valmin;
						}
						$score_id = $tab[$pos+1];
					}
				}
			}
			else{
				if($valmax!=null){
					if($impayes>$valmax){
						if($score_id==SCORE_VERT){
							$insert = 1;
							$motif = " >  ".$valmax;
						}
						$score_id = $tab[$pos+1];
					}
				}
				if($valmin!=null){
					if($impayes<$valmin){
						$score_id = $tab[$pos+1];
					}
				}
			
			}
			if($score_prop!=null)
				$score_prop[0] = $score_id;
			if($insert== 1 ){
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat,'motif'=>$motif);
				$CI->score->insert_resultat_scoring($donnee);
			}
			else{
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
				$CI->score->supprimer_resulat_scoring($donnee);
			}
			/*if($id_score==$score_id){
				$donnee = array("id_pret"=>$id_pret,'id_pret_calcul_scoring'=>$id_resultat);
				$CI->score->insert_resultat_scoring($donnee);
			}*/
			$pos = array_search($id_score,$tab);
			$pos2 = array_search($score_id,$tab);
			if($pos2>$pos){
				$id_score = $score_id;
				$pos = $pos2;
			}
			
		return $id_score;
	}
}
if (!function_exists('Calculate_score')) {
	function Calculate_score($id_pret,$duree = 0,$montant=0,$via_produit = false,$taux_charge = 0){
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$CI->score->delete_motif_score($id_pret);
		if($duree==0){
			$duree = $CI->score->get_nb_duree($id_pret);
		}
		$temp_id_score = 0;
		$fonc = $CI->score->get_function();
		$id_score = SCORE_VERT;
		for($i=0;$i<count($fonc);$i++)
		{
			
			$info = array(
					"id_score"=>$id_score,"ordre"=>$fonc[$i]->ordre,
					"duree"=>$duree,
					"montant"=>$montant,
					"taux_charge"=>$taux_charge,
					"via_produit"=>$via_produit
			);
		
		//var_dump($fonc[$i]->critere_function);
			$id_score=call_user_func($fonc[$i]->critere_function,$id_pret,$info);
			
			
			if($id_score==0 || $id_score==97) {
				break;
			}
		}
		
		if($id_score>0 && ($id_score== SCORE_VERT || $id_score== 97 )){
			$garantie = 0;
			$array_garantie = array("94","96");
			if(in_array($id_score,$array_garantie)){
				$garantie = get_garantie_pret($id_pret);
				creation_piece_jointe($id_pret,$id_score,$garantie);
			}
			
			creation_piece_jointe($id_pret,$id_score,$garantie);
		}
		
	
		return $id_score;
	}
}

//Pour la proposition de score : Nampoina
if (!function_exists('get_best_score')) {
	function get_best_score($id_pret,$duree=0){
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$fonc = $CI->score->get_function();
		
		
		$id_score = SCORE_VERT;
		$montant = 0;
		$tab_score = array();
		$tab_prop_duree = array();
		$t = 0;
		$j=0;
		for($i=0;$i<count($fonc);$i++)
		{
			
			$info = array(
					"id_score"=>$id_score,"ordre"=>$fonc[$i]->ordre,
					"duree"=>$duree
			);
			$prop_score = 0;
			$prop_duree = 12;
			
			if($fonc[$i]->critere_function!="get_montant_credit"){
				$id_score=call_user_func($fonc[$i]->critere_function,$id_pret,$info,array(&$prop_score),array(&$prop_duree));
				
				
				if($prop_score!=0) $tab_score[$j]=$prop_score;
				if($prop_duree!=0) $tab_prop_duree[$t] = $prop_duree;
				$j++;
				$t++;
			}
		}
		$data_retour= array();
		if(sizeof($tab_score)>0 && sizeof($tab_prop_duree)>0){
			$score_temp= max($tab_score);
			$duree_temp = max($tab_prop_duree);
			
			$data_retour['id_score']= $score_temp;
			$data_retour['duree'] = $duree_temp;
			$montant = get_montant_proposition_score($duree_temp,$score_temp);
		
			$data_retour['montant'] = $montant ;
		}
		else{
			$data_retour['id_score']= 0;
			$data_retour['duree'] = 0;
			$data_retour['montant'] = 0 ;
		}
		return $data_retour;
		
		$tab_score = array_unique($tab_score);
		$tab_prop_duree = array_unique($tab_prop_duree);
		
		/*$ordre_score = array(SCORE_VERT,SCORE_ORANGE,SCORE_ROUGE,SCORE_ORANGE);
		$bas = count($tab_score)-1;
		for($i=0;$i<count($tab_score);$i++){
			if(isset($tab_score[$i])){
				$new_pos = array_search($tab_score[$i],$ordre_score);
				
				
				if($new_pos<=$bas){
					$bas = $new_pos;
				}
				if($bas==0) break;
			}
		}
		
		$best_score = $ordre_score[$bas];
		
		return get_montant_proposition_score($duree,$best_score);*/
	}
}

if (!function_exists('get_montant_proposition_score')){
	function get_montant_proposition_score($duree,$id_score){
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$tab = array('duree'=>$duree,"score"=>$id_score );
		$infos = $CI->score->get_info_by_score_and_montant_credit($tab);
		
		
		if(count($infos)!=0){
			if(count($infos)>1){
				if($infos[0]->valeur_max==null)
				$max = $infos[0]->valeur_min;
				else $max = $infos[0]->valeur_max;
				for($i=0;$i<count($infos);$i++){
					if($infos[$i]->valeur_max==null){
						if($infos[$i]->valeur_min>$max) $max = $info[$i]->valeur_min;
					}
					else{
						if($infos[$i]->valeur_max>$max) $max = $infos[$i]->valeur_max;
					}
				}
			}
			else{
				if($infos[0]->valeur_max==null)
				$max = $infos[0]->valeur_min;
				else $max = $infos[0]->valeur_max;
			}
			return $max;
		}
		else return 0;
	}
}
if (!function_exists('calcul_convention')){
	function calcul_convention($id_employeur ,$duree,$montant, $taux){
		$CI =& get_instance();
		$CI->load->model('demande_cred/score_model','score');
		$CI->load->model('demande_cred/client_model','client');
		$CI->load->model('base_rcx/base_rcx_model','rcx');
		if($duree>=12 && $duree<=24){
			$convention =  "conv12_24_mois";
		}
		else if($duree<12){
			$convention = "conv11_mois";
		}
		else if($duree>=25 && $duree<=60){
			$convention = "conv25_60_mois"; 
		}
		else {
			$convention = null;
		}
		if($convention==null){
			return 0;
		}
        
        $reduction  = 0;
        $taux_convention = 0;
        
		$conv = $CI->rcx->get_taux_convention($id_employeur,$convention);		
        $reduction = $conv->if_inf > 0 && $montant < $conv->seuil ? $conv->if_inf :  $conv->defaut;
        
       if ($conv->unite == 1 ) $reduction = $taux * $reduction/100;
       $taux_convention = $taux  -   $reduction;
       		
       return $taux_convention;
	}
}

if(!function_exists("get_all_user_by_criteres")){
	function get_all_user_by_criteres($criteres){
		$CI =& get_instance();
		$CI->load->model('admin/user_accounts_model', 'user');
		$users = $CI->user->get_all_users(10,0,$criteres);
		return $users["rows"];
	}
}
if(!function_exists("get_user_by_id_mail")){
	function get_user_by_id_mail($uacc_id){
		$CI =& get_instance();
		$CI->load->model('admin/user_accounts_model', 'user');
		$users = $CI->user->get_user_by_id($uacc_id);
		return $users;
	}
}

if (!function_exists('get_working_days')) {
	function get_working_days($datedeb,$datefin){
		$nb_jours=0;
		$dated=explode('-',$datedeb);
		$datef=explode('-',$datefin);
		$timestampcurr=mktime(0,0,0,$dated[1],$dated[2],$dated[0]);
		$timestampf=mktime(0,0,0,$datef[1],$datef[2],$datef[0]);
		while($timestampcurr<$timestampf){
			if((date('w',$timestampcurr)!=0)&&(date('w',$timestampcurr)!=6)){
				$nb_jours++;
			}
			$timestampcurr=mktime(0,0,0,date('m',$timestampcurr),(date('d',$timestampcurr)+1)   ,date('Y',$timestampcurr));
		}
		return $nb_jours;
	}
}
if(!function_exists("get_liste_garantie_by_pret")){
	function get_liste_garantie_by_pret($id_pret){
		$CI =& get_instance();
		$CI->load->model('garantie/garantie_nantissement_model', 'nantissement');
		$criteres = array("id_pret"=>$id_pret);
		$garantie = $CI->nantissement->get_all_garantie($criteres);
		return $garantie;
	}
}
if(!function_exists("get_tous_information_by_mail")){
	function get_tous_information_by_mail($id_pret){
		$CI =& get_instance();
		$CI->load->model('demande_cred/pret_model', 'pret');
		$data_mail = $CI->pret->get_information_by_mail($id_pret);
		return $data_mail[0];
	}
}
if(!function_exists("get_user_dr_or_DFR")){
	function get_user_dr_or_DFR($criteres){
		$CI =& get_instance();
		$CI->load->model('agence/affectation_reseau_model', 'agence');
		$data_mail = $CI->agence->liste_dr_DFR($criteres);
		return $data_mail;
	}
}
if(!function_exists("envoi_mail_echange_interne")){
	function envoi_mail_echange_interne($id_pret,$mail,$data_echange){
		$data_mail = get_tous_information_by_mail($id_pret);
		$CI =& get_instance();
		$subject = "Codification message_".$data_mail["li_employeur"]."_".$data_mail["score"]."_FC_".$data_mail["ref_dossier"]."_".$data_mail["radical"]."_".$data_mail["cle"]."_".$data_mail["nom"].":".$data_echange['objet'];
		$data_mail['dasboard'] = 0;
		$data_mail["uacc_id"] = $mail["uacc_id"];
		$data_mail["expedi"] = $data_echange["expedi"];
		$data_mail["fonction"] = $data_echange["fonction"];
		$data_mail["contenu"] = $data_echange["contenu"];
		
		$data_mail["nom_et_prenom"] = strtoupper($mail["nom"])." ".$mail["prenom"];
		$from_email = $mail["from_mail"];
		$to_email = $mail["to_email"];
		$from_name = $mail["from_name"];
		$message= $CI->load->view("mail/echange_interne.tpl.php",$data_mail,true);
		send_email_without_template($from_email, $to_email, $from_name, $subject, $message);
		
	}
}
if(!function_exists("envoi_mail_notification")){
	function envoi_mail_notification($id_pret,$mail=array(),$reserve ="",$data_mail = array()){
		if(sizeof($data_mail)==0){
			$data_mail = get_tous_information_by_mail($id_pret);
		}
		$CI =& get_instance();
		$data_mail["reserve"] = $reserve;
		$data_mail["uacc_id"] = $mail["uacc_id"];
		$data_mail["nom_et_prenom"] = strtoupper($mail["nom"])." ".$mail["prenom"];
		$data_mail["dasboard"] = $mail["dasboard"];
		/* initialisation donnees */
		$from_email = $mail["from_mail"];
		$to_email = $mail["to_email"];
		$from_name = $mail["from_name"];
		$subject = "Codification avis_".$data_mail["li_employeur"]."_".$data_mail["score"]."_FC_".$data_mail["ref_dossier"]."_".$data_mail["radical"]."_".$data_mail["cle"]."_".$data_mail["nom"]."_".$data_mail["statut"];
		$message= $CI->load->view("mail/template_mail.tpl.php",$data_mail,true);
		
		send_email_without_template($from_email, $to_email, $from_name, $subject, $message);
	}
}
if(!function_exists("get_data_deblocage")){
	function get_data_deblocage($id_pret){
		$CI =& get_instance();
		$CI->load->model('deblocage_credit/deblocage_credit_model', 'deblocage_credit');
		$criteres_deb = array("id_pret"=>$id_pret);
		/*test par id */
		$data_deblocage= $CI->deblocage_credit->get_test_deblocage_par_criteres($criteres_deb);
		return $data_deblocage[0];
	}
}
if(!function_exists("traitement_exportation_deblocage")){
	function traitement_exportation_deblocage(&$active_sheet, $type, $id_pret){
			/*reception donnees */
			$data_deblocage = get_data_deblocage($id_pret);
			
			$garantie = get_liste_garantie_by_pret($id_pret);
		
			/*entete */
			
			$active_sheet->getCell('AG2')->setValueExplicit( "Réf dossier : ".$data_deblocage['ref_dossier'], $type);
			$active_sheet->getCell('O7')->setValueExplicit(isset($data_deblocage["dt_rec_aro"])? $data_deblocage["dt_rec_aro"] : "" , $type);
			$active_sheet->getCell('Q7')->setValueExplicit(isset($data_deblocage["dt_dec"])? $data_deblocage["dt_dec"]: "" , $type);
			$active_sheet->getCell('AG7')->setValueExplicit(isset($data_deblocage["dt_env_coc"])? $data_deblocage["dt_env_coc"]:"" , $type);
			$active_sheet->getCell('O8')->setValueExplicit(isset($data_deblocage["dt_rec_coc"])? $data_deblocage["dt_rec_coc"]:"" , $type);
			$active_sheet->getCell('Q8')->setValueExplicit(isset($data_deblocage["dt_rec_garantie"])? $data_deblocage["dt_rec_garantie"]:"" , $type);
			$active_sheet->getCell('AG8')->setValueExplicit(isset($data_deblocage["dt_lev_reserve"])? $data_deblocage["dt_lev_reserve"]:"" , $type);
			$active_sheet->getCell('O9')->setValueExplicit(isset($data_deblocage["dt_rec_doc_complet"])? $data_deblocage["dt_rec_doc_complet"]:"" , $type);
			$active_sheet->getCell('Q9')->setValueExplicit(isset($data_deblocage["dt_debloque_credit"])? $data_deblocage["dt_debloque_credit"]:"" , $type);
			/*garantie */
			$count = sizeof($garantie);
			for($i = 0 ;$i<$count;$i++){
				$active_sheet->getCell("N".($i+14))->setValueExplicit($garantie[$i]["type_garantie"], $type);
				$active_sheet->getCell("O".($i+14))->setValueExplicit($garantie[$i]["li_garantie"], $type);
				$active_sheet->getCell("Q".($i+14))->setValueExplicit($garantie[$i]["recueilli"], $type);
				$active_sheet->getCell("R".($i+14))->setValueExplicit($garantie[$i]["date_reception"], $type);
				$active_sheet->getCell("AG".($i+14))->setValueExplicit(number_format($garantie[$i]["montant_garantie"],2,'.',' '), $type);
			}
	}
}
if (!function_exists('traitement_demande_simple_edition')) {
    function traitement_demande_simple_edition(&$active_sheet, $type, $idPret) {
    	//CRA : 18/02/2016 : récupérer les données en utilisant $idPret
    	//Continuer le remplissage des fichiers selon le modèle
		$data_pret = get_information_edition_by_id_pret($idPret);
		/*client emprunteur */
    	$active_sheet->getCell('B8')->setValueExplicit($data_pret["no_compte"], $type);
		$active_sheet->getCell('N8')->setValueExplicit($data_pret["dt_ouverture"], $type);
		$active_sheet->getCell('D9')->setValueExplicit($data_pret["li_nom"], $type);
		
		$lettre = day_en_lettre_edition();
		$active_sheet->getCell('A76')->setValueExplicit($lettre , $type);
		
		$active_sheet->getCell('V9')->setValueExplicit($data_pret["li_prenom"], $type);
		$active_sheet->getCell('B10')->setValueExplicit($data_pret["dt_naissance"], $type);
		$active_sheet->getCell('K10')->setValueExplicit($data_pret["li_lieu_naissance"], $type);
		$active_sheet->getCell('B11')->setValueExplicit($data_pret["nationalite"], $type);
		$active_sheet->getCell('L11')->setValueExplicit($data_pret["stat_marital"], $type);
		$active_sheet->getCell('AD11')->setValueExplicit($data_pret["nb_personnes_a_charge"], $type);
		$active_sheet->getCell('B12')->setValueExplicit($data_pret["li_adresse_domicile"], $type);
		$active_sheet->getCell('B13')->setValueExplicit($data_pret["li_telephone_domicile"], $type);
		$active_sheet->getCell('J13')->setValueExplicit($data_pret["li_fax_domicile"], $type);
		$active_sheet->getCell('S13')->setValueExplicit($data_pret["li_email"], $type);
		$active_sheet->getCell('B14')->setValueExplicit($data_pret["li_telephone_portable"], $type);
		$cin = explode(" ",$data_pret["identification"]);
		$active_sheet->getCell('A15')->setValueExplicit($cin[0], $type);
		$active_sheet->getCell('B15')->setValueExplicit(substr($data_pret["identification"],3), $type);
		$active_sheet->getCell('F15')->setValueExplicit($data_pret["dt_etablissement"], $type);
		$active_sheet->getCell('L15')->setValueExplicit($data_pret["li_lieu_etablissement_piece_identite"], $type);
		$active_sheet->getCell('AA15')->setValueExplicit($data_pret["li_lieu_duplicata"], $type);
		
		$active_sheet->getCell('B16')->setValueExplicit($data_pret["li_nom_conjoint"], $type);
		$active_sheet->getCell('R16')->setValueExplicit($data_pret["li_fct_conjoint"], $type);
		
		$active_sheet->getCell('B17')->setValueExplicit($data_pret["li_employeur"], $type);
		$active_sheet->getCell('R17')->setValueExplicit($data_pret["li_adresse_domicile"], $type);
		$active_sheet->getCell('B18')->setValueExplicit($data_pret["fonction_emp"],$type);
		$active_sheet->getCell('AD18')->setValueExplicit($data_pret["fonctionnaire"],$type);
		$active_sheet->getCell('B19')->setValueExplicit($data_pret["nature_contrat_emprunteur"], $type);
		$active_sheet->getCell('B20')->setValueExplicit($data_pret["enceinnete_ans"], $type);
		$active_sheet->getCell('K20')->setValueExplicit(number_format($data_pret["mt_salaire_net"],2,'.',' '), $type);
		
		$sal_co = 0;
		$autre_co = 0;
		/*client co-emprunteur */
		if($data_pret['is_co_emprunteur'] == 1){
			$data_pret_co = get_information_edition_by_id_pret($idPret,true);
			$co_emprunteur = $data_pret_co;
		
			$active_sheet->getCell('D28')->setValueExplicit($co_emprunteur["li_nom"], $type);
			$active_sheet->getCell('V28')->setValueExplicit($co_emprunteur["li_prenom"], $type);
			$active_sheet->getCell('B29')->setValueExplicit($co_emprunteur["dt_naissance"], $type);
			$active_sheet->getCell('K29')->setValueExplicit($co_emprunteur["li_lieu_naissance"], $type);
			$active_sheet->getCell('B30')->setValueExplicit($co_emprunteur["nationalite"], $type);
			
			$ide = explode(" ",$co_emprunteur["identification"] );
			//$active_sheet->getCell('A32')->setValueExplicit($ide[0], $type);
			
			$active_sheet->getCell('Q30')->setValueExplicit(substr($co_emprunteur['identification'],3), $type);
			$active_sheet->getCell('B31')->setValueExplicit($co_emprunteur["li_telephone_domicile"],$type);
			$active_sheet->getCell('J31')->setValueExplicit($co_emprunteur["li_fax_domicile"],$type);
			$active_sheet->getCell('S31')->setValueExplicit($co_emprunteur["li_email"],$type);
			//$active_sheet->getCell('B32')->setValueExplicit(substr($co_emprunteur['identification'],3), $type);
			$active_sheet->getCell('F32')->setValueExplicit($co_emprunteur["date_etab_co"], $type);
			
			$active_sheet->getCell('A32')->setValueExplicit(!empty($co_emprunteur['type_piece'])?$co_emprunteur['type_piece']:$ide[0], $type);
			$active_sheet->getCell('B32')->setValueExplicit((!empty($co_emprunteur['piece']) ? $co_emprunteur['piece']:substr($co_emprunteur['identification'],3)), $type);
			
			
			$active_sheet->getCell('L32')->setValueExplicit($co_emprunteur["li_lieu_etablissement_piece_identite"], $type);
			$active_sheet->getCell('AA32')->setValueExplicit($co_emprunteur["li_lieu_duplicata"], $type);
			
			$active_sheet->getCell('AA32')->setValueExplicit($co_emprunteur["li_lieu_duplicata"], $type);
			$active_sheet->getCell('B33')->setValueExplicit($co_emprunteur["li_nom_pere"], $type);
			$active_sheet->getCell('Q33')->setValueExplicit($co_emprunteur["li_nom_mere"], $type);
			$active_sheet->getCell('B34')->setValueExplicit($co_emprunteur["li_employeur"], $type);
			$active_sheet->getCell('R34')->setValueExplicit($co_emprunteur["li_adresse_domicile"], $type);
			$active_sheet->getCell('B35')->setValueExplicit($co_emprunteur["fonction_emp"],$type);
			
			$sal_co = $co_emprunteur["mt_salaire_net"];
			$autre_co =$co_emprunteur["mt_autres_revenus_net"];
			$active_sheet->getCell('B36')->setValueExplicit($co_emprunteur["nature_contrat_emprunteur"],$type);
			$active_sheet->getCell('B37')->setValueExplicit($co_emprunteur["duree_contrat_co"], $type);
			$active_sheet->getCell('J37')->setValueExplicit(number_format($co_emprunteur["mt_salaire_net"],2,'.',' '), $type);
			$active_sheet->getCell('Q40')->setValueExplicit($co_emprunteur["li_autres_sources_revenus"], $type);
			$active_sheet->getCell('Q41')->setValueExplicit(number_format($co_emprunteur["mt_autres_revenus_net"],2,'.',' '), $type);
			
		
		
		
		
		
		}
		$sal_co = $sal_co + $data_pret["mt_salaire_net"];
		$autre_co =$autre_co + $data_pret["mt_autres_revenus_net"];
		$active_sheet->getCell('Z51')->setValueExplicit(number_format($sal_co,2,'.',' '), $type);
		$active_sheet->getCell('Z52')->setValueExplicit(number_format($autre_co,2,'.',' '), $type);
		
		/* */
		
		/*autre source revenues */
		$fin_contrat = date_apres_n_mois($data_pret["dt_debut_pret"],$data_pret["nb_duree_mois"]);
		
		$active_sheet->getCell('B55')->setValueExplicit($data_pret["dt_debut_pret"], $type);
		$active_sheet->getCell('E55')->setValueExplicit($fin_contrat, $type);
		$active_sheet->getCell('C40')->setValueExplicit($data_pret["li_autres_sources_revenus"], $type);
		$active_sheet->getCell('C41')->setValueExplicit(number_format($data_pret["mt_autres_revenus_net"],2,'.',' '), $type);
		
		$active_sheet->getCell('X57')->setValueExplicit(number_format($data_pret["montant_garantie"],2,'.',' '), $type);
		$as = 0;
		if(!empty($data_pret["tx_assurance"])){
			$assurance = explode("%",$data_pret["tx_assurance"]);
			$as = $assurance[0];
		 }
		$t = (($data_pret["tx_annuel_ht"]+ $data_pret["tva"] + $as)/100);
		$mensualite = calcul_mensualite_pret($data_pret["mt_capital_emprunte"], $data_pret["nb_duree_mois"], $t);
		$active_sheet->getCell('Z54')->setValueExplicit(number_format($mensualite,2,'.',' '), $type);
		
		
		/*condition du pret */
		$active_sheet->getCell('B50')->setValueExplicit($data_pret["objet_demande"], $type);
		$active_sheet->getCell('B51')->setValueExplicit(number_format($data_pret["mt_capital_emprunte"],2,'.',' '), $type);
		$active_sheet->getCell('B52')->setValueExplicit(number_format($data_pret["tx_annuel_ht"],2,'.',' '), $type);
		$assurance = explode("%", $data_pret["tx_assurance"]);
		$active_sheet->getCell('E53')->setValueExplicit($assurance[0], $type);
		$active_sheet->getCell('E54')->setValueExplicit(number_format($data_pret["tx_frais_dossier_ttx"],2,'.',' '), $type);
		
		
		/* calul frais dossier */
		
		
		$frais = calculate_frais_dossier_finale($data_pret);
		$active_sheet->getCell('B54')->setValueExplicit(number_format($frais ,2,'.',' '), $type);
		$active_sheet->getCell('Z55')->setValueExplicit($data_pret["nb_duree_mois"], $type);
		
		$active_sheet->getCell('B56')->setValueExplicit($data_pret["li_garantie"], $type);
		/* engagement encours */
		if(sizeof($data_pret["liste_engagement_cours"]) > 0){
			$encours = $data_pret["liste_engagement_cours"];
			$active_sheet->getCell('A64')->setValueExplicit($encours[0]["objet_demande"], $type);
			$active_sheet->getCell('D64')->setValueExplicit("BNI", $type);
			$active_sheet->getCell('L64')->setValueExplicit(number_format($encours[0]["mt_capital_emprunte"],2,'.',' '), $type);
			$active_sheet->getCell('W64')->setValueExplicit($encours[0]["nb_duree_mois"], $type);
			$active_sheet->getCell('Z64')->setValueExplicit($encours[0]["mensualite"],$type);
			$active_sheet->getCell('AC64')->setValueExplicit($encours[0]["en_cours"], $type);
			if(!empty($encours[1])){
				
				$active_sheet->getCell('D65')->setValueExplicit("BNI", $type);
				$active_sheet->getCell('A65')->setValueExplicit($encours[1]["objet_demande"], $type);
				$active_sheet->getCell('L65')->setValueExplicit(number_format($encours[1]["mt_capital_emprunte"],2,'.',' '), $type);
				$active_sheet->getCell('W65')->setValueExplicit($encours[1]["nb_duree_mois"], $type);
				$active_sheet->getCell('Z65')->setValueExplicit($encours[1]["mensualite"],$type);
				$active_sheet->getCell('AC65')->setValueExplicit($encours[1]["en_cours"], $type);
					
			}
		}
		
		
		
    }
}    
if(!function_exists("calculate_frais_dossier")){
	function calculate_frais_dossier($capital,$taux,$data_pret=array()){
		$frais = (($taux)*$capital)/100;
		if($frais<15000){
				$frais = 15000;
		}
		if($frais>500000) {
				$frais = 500000;
		}
		return $frais;
		
		
	}
}
if(!function_exists("calculate_frais_dossier_finale")){
	function calculate_frais_dossier_finale($data_pret = array()){
		$frais = calcul_frais_dossier($data_pret['id_pret'],$data_pret['mt_capital_emprunte'],$data_pret['nb_duree_mois'],$data_pret['tx_frais_dossier'],0,$data_pret['mt_plafond'],$data_pret['mt_plancher']);
		return $frais ;
	}
}
if(!function_exists("calculate_qc_autre_revenu")){
	function calculate_qc_autre_revenu($mt_autres_revenus_net, $mt_salaire_net){
		$qc_autres_revenus = $mt_autres_revenus_net == 0 ? ($mt_salaire_net*35)/100 : ($mt_salaire_net*35)/100 < $mt_autres_revenus_net ?  ($mt_salaire_net*35)/100 : $mt_autres_revenus_net ;
		return $qc_autres_revenus;
	}
}
if (!function_exists('traitement_synthese_demande_edition')) {
    function traitement_synthese_demande_edition(&$active_sheet, $type, $idPret) {
    	//CRA : 18/02/2016 : récupérer les données en utilisant $idPret
    	//Continuer le remplissage des fichiers selon le modèle
    	//Attention : La valeur de ce champ doit être ROUGE - VERT ou ORANGE
        
       	$CI =& get_instance();
		$CI->load->model('demande_cred/edition_model');
	   
        $data = $CI->edition_model->get_synthese_data($idPret);
		
		$fin_contrat = date_apres_n_mois($data["dt_debut"],$data["nb_duree_mois"]);
       
		
        $tx_assurance = extraire_taux($data['tx_assurance']);
        $taux = calculate_taux($data['tx_annuel_ht'],$data['tva'],$tx_assurance);                        
        $mensualite = calcul_mensualite_pret($data["mt_capital_emprunte"], $data["nb_duree_mois"], $taux);
		
		
        $ttc_hors_assurance = calculate_taux($data['tx_annuel_ht'],$data['tva'],0);
        $mensualite_hors_assurance  = calcul_mensualite_pret($data["mt_capital_emprunte"], $data["nb_duree_mois"], $ttc_hors_assurance);               
        $assurance =$mensualite - $mensualite_hors_assurance;
        
        //var_dump($mensualite);
        /*
        Total autres revenus = somme (QC autres revenus emprunteur + QC autres revenus co-emprunteur)
        
        QC autres revenus = 35%Montant salaire net
         si < Montant autres revenus nets 
         sinon Montant autres revenus nets */
         
         //var_dump($data);
         
        $qc_autres_revenus = $data['mt_autres_revenus_net'] == 0 ? 0 : ($data['mt_salaire_net']*35)/100 < $data['mt_autres_revenus_net'] ?  ($data['mt_salaire_net']*35)/100 : $data['mt_autres_revenus_net'] ;
        $qc_autres_revenus_co = $data['mt_autres_revenus_net_co'] == 0 ?0 : ($data['mt_salaire_net_co']*35)/100 < $data['mt_autres_revenus_net_co'] ?  ($data['mt_salaire_net_co']*35)/100 : $data['mt_autres_revenus_net_co'] ; ;
       
        $total_autres_revenu = $qc_autres_revenus + $qc_autres_revenus_co;
        
        //var_dump($total_autres_revenu);
        $quotitte_cessible = calculate_qc_autre_rev ($idPret);
        
        //$taux_endettement_reel = ($mensualite/($data['mt_autres_revenus_net']+$data['mt_salaire_net']+ $data['mt_autres_revenus_net_co']))*100;
        //$taux_endettement_reel = round(($mensualite/( $data['mt_salaire_net']+ $data['mt_salaire_net_co'] + $total_autres_revenu))*100,2);
		

        ///$taux_endettement_reel = calculate_taux_endettement_reel($data["mt_capital_emprunte"],$data["nb_duree_mois"],$data['tx_annuel_ht'],$data['tva'],$tx_assurance,$data['mt_salaire_net'],
        //$data['mt_autres_revenus_net'],$data['mt_autres_revenus_net_co']);
        
        //var_dump($taux_endettement_reel);
        $qc_menage = calculate_qc_menage($idPret);
        $taux_max = 0.33;
		
		$taux_endettement_reel = ($mensualite*33)/( $qc_menage);
        //var_dump($data['charge_menage']);
         //charge_menage = enleveMillier_temp($('#score_charges_menage').val());		$tx_encours = (charge_menage*taux_max/qc_menage)*100;
        $taux_endettement_encours= round(($data['charge_menage']*$taux_max/$qc_menage)*100,2);         
        
       /* var_dump($data);
        var_dump($qc_menage);
        var_dump($taux_endettement_reel);
        var_dump($taux_endettement_encours);*/
        $total_taux_endettement = $taux_endettement_reel+ $taux_endettement_encours;
        //$taux_applique = calculate_taux_ttc($data['tx_annuel_ht'],$data['tva']);
        $taux_applique = calculate_taux_ttc ($data['tx_annuel_ht'] + $data['tva']);
        $total_mensualites  = $data['mt_salaire_net'] + $data['mt_autres_revenus_net'];
        $mesualites = $mensualite_hors_assurance;
        
    	$active_sheet->getCell('B11')->setValueExplicit($data['score'], $type);
        $active_sheet->getCell('B4')->setValueExplicit(number_format( $data['mt_capital_emprunte'],2,',',' '), $type);
        $active_sheet->getCell('B5')->setValueExplicit($data['nb_duree_mois'], $type);
        $active_sheet->getCell('B6')->setValueExplicit(number_format($mensualite_hors_assurance,2), $type);
        $active_sheet->getCell('B7')->setValueExplicit(number_format($assurance,2,',',' '),$type);
        $active_sheet->getCell('B8')->setValueExplicit(number_format($quotitte_cessible,2,',',' '), $type);
        $active_sheet->getCell('B9')->setValueExplicit(number_format($total_taux_endettement,2,',',' '), $type);
        $active_sheet->getCell('B10')->setValueExplicit($data['nb_garanties'], $type); 
        
        $active_sheet->getCell('I4')->setValueExplicit($data['objet_cap'], $type);
        $active_sheet->getCell('I5')->setValueExplicit(round($taux_applique*100,2), $type);        
        $active_sheet->getCell('I6')->setValueExplicit($data["dt_debut"], $type);
        $active_sheet->getCell('M6')->setValueExplicit($fin_contrat, $type);
        
        $active_sheet->getCell('J7')->setValueExplicit(number_format($mesualites,2,',',' '), $type);  
        $active_sheet->getCell('J8')->setValueExplicit(number_format( $mensualite,2,',',' '), $type);      
        $active_sheet->getCell('M10')->setValueExplicit(join(', ',$data['garanties']));
        
        if(count($data['garanties_complementaires'])> 0){
            $garanties = $data['garanties_complementaires'];
            $index = 0;
            
            while ( $index < count($garanties)){
                
                $row = $index +13 ;
                $active_sheet->getCell('A'.$row)->setValueExplicit($garanties[$index]->garantie_type, $type);
                $active_sheet->getCell('G'.$row)->setValueExplicit($garanties[$index]->li_garantie, $type);
                $active_sheet->getCell('M'.$row)->setValueExplicit($garanties[$index]->recueilli, $type);
                $index = $index+1;
            }
        }
        
       // exit();
    }
}

if (!function_exists('traitement_notice_edition')) {
    function traitement_notice_edition(&$active_sheet, $type, $idPret) {
    	//CRA : 18/02/2016 : récupérer les données en utilisant $idPret
    	//Chercher le nom de l'emprunteur 
    	//$nomEmprunteur =  "RAZAFIMAMONJY Caroline"; 
        	$CI =& get_instance();
		$CI->load->model('demande_cred/edition_model');
        $data = $CI->edition_model->get_notice_data($idPret);
        $nomEmprunteur = $data['li_nom'].' '.$data['li_prenom'];
    	$now = new DateTime("now");		
        $signatureEmprunteur = $nomEmprunteur . " " . $now->format("d/m/Y");    	
    	$active_sheet->getCell('A12')->setValueExplicit($active_sheet->getCell('A12')->getValue() . ' '.$nomEmprunteur, $type);
    	$active_sheet->getCell('C77')->setValueExplicit($signatureEmprunteur, $type);
        
        //exit();
    }
}

if (!function_exists('traitement_qms_edition')) {
    function traitement_qms_edition(&$active_sheet, $type, $idPret, $isCoEmprunteur = false) {
    	//CRA : 18/02/2016 : récupérer les données en utilisant $idPret
    	//Mettre le titre du document
    	$titre =  ($isCoEmprunteur) ? "IDENTIFICATION CLIENT (Co-Emprunteur)" : "IDENTIFICATION CLIENT (Emprunteur)";    	
    	$active_sheet->getCell('A7')->setValueExplicit($titre, $type);
		$data_pret = get_information_edition_by_id_pret($idPret,$isCoEmprunteur);
		$lettre = day_en_lettre_edition();
		$active_sheet->getCell('L230')->setValueExplicit($lettre , $type);
		
		
		$active_sheet->getCell('C8')->setValueExplicit(strtoupper($data_pret['li_nom']) , $type);
		$active_sheet->getCell('C9')->setValueExplicit($data_pret['li_prenom'] , $type);
		$active_sheet->getCell('C10')->setValueExplicit($data_pret['li_nom_jeune_fille'] , $type);
		$active_sheet->getCell('C11')->setValueExplicit($data_pret['li_adresse_domicile'] , $type);
		$active_sheet->getCell('C12')->setValueExplicit($data_pret['fonction_emp'], $type);
		$active_sheet->getCell('C13')->setValueExplicit($data_pret['dt_naissance'] , $type);
		$ide = explode(" ",$data_pret["identification"] );
		$active_sheet->getCell('B14')->setValueExplicit(!empty($data_pret['type_piece'])? $data_pret['type_piece']:$ide[0], $type);
		$active_sheet->getCell('C14')->setValueExplicit((!empty($data_pret['piece']) ? $data_pret['piece']:substr($data_pret['identification'],3)) , $type);
		
		$active_sheet->getCell('C15')->setValueExplicit($data_pret['dt_etablissement'] , $type);
		$active_sheet->getCell('C17')->setValueExplicit($data_pret['no_compte'] , $type);
		$active_sheet->getCell('C18')->setValueExplicit($data_pret['li_agence'] , $type);
		$active_sheet->getCell('O8')->setValueExplicit(number_format($data_pret['mt_capital_emprunte'],2,'.',' '), $type);
		$active_sheet->getCell('O9')->setValueExplicit($data_pret['nb_duree_mois'] , $type);
		
		
		
		/* decompte de la prime */
		$decompte_prime = calcul_decompte_prime($data_pret);
		$assurance_taux = $data_pret["tx_assurance"];
		$active_sheet->getCell('C22')->setValueExplicit($assurance_taux , $type);
		$active_sheet->getCell('C23')->setValueExplicit(number_format($decompte_prime,2,'.',' ')." MGA" , $type);
		
	
		
    	//Continuer le remplissage des fichiers selon le modèle    	
    }
}

if(!function_exists("calcul_decompte_prime")){

	function calcul_decompte_prime($data_pret){
		$as = 0;
		if(!empty($data_pret["tx_assurance"])){
			$assurance = explode("%",$data_pret["tx_assurance"]);
			$as = $assurance[0];
		 }
		$assurance_taux = $data_pret["tx_assurance"];
		$t = ($data_pret["tx_annuel_ht"]+ $data_pret["tva"] + $as)/100;
		$t_s = ($data_pret["tx_annuel_ht"]+ $data_pret["tva"])/100;
		$mensualite_avec = calcul_mensualite_pret($data_pret["mt_capital_emprunte"], $data_pret["nb_duree_mois"], $t);
		$mensualite_sans = calcul_mensualite_pret($data_pret["mt_capital_emprunte"], $data_pret["nb_duree_mois"], $t_s);
		$decompte_prime = $mensualite_avec - $mensualite_sans;
		return $decompte_prime;
	}
}
if(!function_exists("calcul_mensualite_pret")){
	function calcul_mensualite_pret($kapital, $durre, $taux){
	$mensulite = 0;
	if($kapital>0 && $durre>0){
		$mensulite = ($kapital*$taux/12)/(1- pow((1+($taux/12)),-$durre));
		
	}
	return $mensulite;
}
}
if(!function_exists("date_apres_n_mois")){
	function date_apres_n_mois($date, $mois){
		$mois = $mois-1;
		$date =date_fr_to_en($date);
		$format = "d/m/Y";
		$date_fr = date($format, strtotime($date. ' + '.$mois.' month'));
		return $date_fr;
	}
}


if (!function_exists('traitement_dpa_vie_edition')) {
    function traitement_dpa_vie_edition(&$active_sheet, $type, $idPret, $isCoEmprunteur = false,$signature="") {
    	//VOIR LE MODELE INITIAL POUR REFERER AUX CHAMPS A REMPLIR
    	//CRA : 18/02/2016 : récupérer les données en utilisant $idPret
    	//Mettre le titre du document
		$les_madame = $signature;
    	$titre =  ($isCoEmprunteur) ? "Le Co-Emprunteur," : "L'emprunteur,";
    	$signature =  ($isCoEmprunteur) ? "Le Co-Emprunteur" : "L'Emprunteur";
		$data_pret = get_information_edition_by_id_pret($idPret,$isCoEmprunteur);
		$active_sheet->getCell('A11')->setValueExplicit($les_madame, $type);
    	$active_sheet->getCell('C27')->setValueExplicit($titre, $type);
		/* titre */
		$lettre = day_en_lettre_edition();
		$active_sheet->getCell('I168')->setValueExplicit($lettre , $type);
		$nom_titre = " - ". $data_pret["qualite"]." ". strtoupper($data_pret["li_nom"])." " .$data_pret["li_prenom"] .", ".$data_pret["fonction_emp"]." auprès de ".$data_pret['li_employeur']." ,né/née le ".$data_pret["dt_naissance"] ." à ".$data_pret["li_lieu_naissance"].", ";
		
		$info_parent = " fils/fille de ".$data_pret["li_nom_pere"]." et de ".$data_pret["li_nom_mere"];
		$active_sheet->getCell('A18')->setValueExplicit($nom_titre, $type);
		$active_sheet->getCell('B20')->setValueExplicit($info_parent, $type);
		$ide = explode(" ",$data_pret["identification"] );
		$active_sheet->getCell('G21')->setValueExplicit((!empty($data_pret['piece']) ? $data_pret['piece']:substr($data_pret['identification'],3)), $type);
		$active_sheet->getCell('I21')->setValueExplicit($data_pret["dt_etablissement"], $type);
		
		$active_sheet->getCell('D35')->setValueExplicit($data_pret["qualite"], $type);
		$active_sheet->getCell('E35')->setValueExplicit(strtoupper($data_pret["li_nom"]), $type);
		$active_sheet->getCell('G35')->setValueExplicit($data_pret["li_prenom"], $type);
		$active_sheet->getCell('D36')->setValueExplicit(" à court terme de MGA ".number_format($data_pret["mt_capital_emprunte"],2,"."," "), $type);
		
		
		
		$montant_avec_virgule = explode(".",$data_pret["mt_capital_emprunte"]);
		$m1 = $montant_avec_virgule[0];
		$mont_en_lettre =conversion_en_lettre($m1 );
		if(!empty($montant_avec_virgule[1])){
			$m2 = substr($montant_avec_virgule[1],0,2);
			//var_dump($m2); exit();
			$m_2 = conversion_en_lettre($m2);
			
			$mont_en_lettre .= "virgule ".$m_2;
		}
		$active_sheet->getCell('B37')->setValueExplicit(strtoupper($mont_en_lettre)." ARIARY", $type);
		$active_sheet->getCell('F47')->setValueExplicit(number_format($data_pret["tx_annuel_ht"],2,'.',' '). " %", $type);
		$active_sheet->getCell('C72')->setValueExplicit($data_pret["no_compte"], $type);
		$as = 0;
		if(!empty($data_pret["tx_assurance"])){
			$assurance = explode("%",$data_pret["tx_assurance"]);
			$as = $assurance[0];
		 }
		$t = ($data_pret["tx_annuel_ht"]+ $data_pret["tva"] + $as)/100;
		$mois_en_lettre = conversion_en_lettre($data_pret["nb_duree_mois"]);
		$active_sheet->getCell('C66')->setValueExplicit(strtoupper($mois_en_lettre), $type);
		$mensualite = calcul_mensualite_pret($data_pret["mt_capital_emprunte"], $data_pret["nb_duree_mois"], $t);
		$active_sheet->getCell('C67')->setValueExplicit(number_format($mensualite,2,'.',' '), $type);
		$montant_avec_virgule = explode(".",$mensualite);
		$m1 = $montant_avec_virgule[0];
		$mont_en_lettre =conversion_en_lettre($m1 );
		if(!empty($montant_avec_virgule[1])){
			$m2 = substr($montant_avec_virgule[1],0,2);
			$m_2 = conversion_en_lettre($m2 );
			$mont_en_lettre .= "virgule ".$m_2;
		}
		
		
		
		$active_sheet->getCell('C68')->setValueExplicit(strtoupper($mont_en_lettre) ." ARIARY ", $type);
		
		/*debut et fin */
		$fin_contrat = date_apres_n_mois($data_pret["dt_debut_pret"],$data_pret["nb_duree_mois"]);
		
		$active_sheet->getCell('C69')->setValueExplicit($data_pret["dt_debut_pret"], $type);
		$active_sheet->getCell('E69')->setValueExplicit($fin_contrat, $type);
		$active_sheet->getCell('F77')->setValueExplicit($data_pret["li_employeur"], $type);
		
		
		
		
		/*delegation view */
		
		$nom_emprunteur = $data_pret["qualite"]." ". strtoupper($data_pret["li_nom"])." " .$data_pret["li_prenom"];
		$active_sheet->getCell('A142')->setValueExplicit($nom_emprunteur , $type);
		$active_sheet->getCell('A149')->setValueExplicit($nom_emprunteur , $type);
		$active_sheet->getCell('A152')->setValueExplicit(" - " .$nom_emprunteur ." à son domicile cité ci-dessus" , $type);
		
    	$active_sheet->getCell('E185')->setValueExplicit($signature, $type);
		
		$active_sheet->getCell('D181')->setValueExplicit($les_madame, $type);
		$active_sheet->getCell('D194')->setValueExplicit($nom_emprunteur, $type);
		
		
		
    	//Continuer le remplissage des fichiers selon le modèle    	
    }
}
if (!function_exists('traitement_kyc_edition')) {
    function traitement_kyc_edition(&$active_sheet, $type, $idPret) {
    	//VOIR LE MODELE INITIAL POUR REFERER AUX CHAMPS A REMPLIR
    	//CRA : 18/02/2016 : récupérer les données en utilisant $idPret
    	//Chercher l'agence
    	$nomAgence =  "01 - Ampefiloha"; 
        $CI =& get_instance();
		$CI->load->model('demande_cred/edition_model');
	    $data = $CI->edition_model->get_kyc_data($idPret);
        $agence = $data['cd_agence']. '-'.$data['li_agence'];
    	$active_sheet->getCell('D9')->setValueExplicit($agence, $type);
   		$active_sheet->getCell('D10')->setValueExplicit(date('d/m/Y'), $type); 
    	$active_sheet->getCell('C29')->setValueExplicit($data['li_nom'], $type);
    	$active_sheet->getCell('C30')->setValueExplicit($data['li_prenom'], $type);
     	$active_sheet->getCell('C31')->setValueExplicit( $data['dt_naissance'] , $type);
     	$active_sheet->getCell('D31')->setValueExplicit( ''.$data['li_lieu_naissance'], $type);
        $active_sheet->getCell('C32')->setValueExplicit( $data['profession'] , $type);
        $active_sheet->getCell('C33')->setValueExplicit( $data['nationalite'] , $type);
        $active_sheet->getCell('C34')->setValueExplicit( $data['li_adresse'] , $type);
        $active_sheet->getCell('C35')->setValueExplicit( $data['telephone'] , $type);
        $active_sheet->getCell('F35')->setValueExplicit( $data['fax'] , $type);
        $active_sheet->getCell('H35')->setValueExplicit( $data['email'] , $type);
        $active_sheet->getCell('C39')->setValueExplicit( $data['situation_famille'] , $type);
        $active_sheet->getCell('G39')->setValueExplicit( $data['nb_personnes_a_charge'] , $type);
        $active_sheet->getCell('C40')->setValueExplicit( $data['li_employeur'] , $type);
        $active_sheet->getCell('C41')->setValueExplicit( $data['salaire_mensuel'] , $type);
        $active_sheet->getCell('C42')->setValueExplicit( $data['autres_activites'] , $type);
        $active_sheet->getCell('C43')->setValueExplicit( $data['autre_revenu'] , $type);
        $active_sheet->getCell('G49')->setValueExplicit( $data['radical'] , $type);
        $active_sheet->getCell('H49')->setValueExplicit( $data['cle'] , $type);
        $active_sheet->getCell('J19')->setValueExplicit( $data['fc'] , $type);
        
        //exit();
    }
}

if (!function_exists('traitement_refus_edition')) {
    function traitement_refus_edition(&$active_sheet, $type, $idPret) {
    	//VOIR LE MODELE INITIAL POUR REFERER AUX CHAMPS A REMPLIR
    	//CRA : 18/02/2016 : récupérer les données en utilisant $idPret
    	//Chercher l'agence
        //NOFY: On ne trouve pas le nom de la ville de l' agence dans la table agence
    	//$adresseAgence =  "Antananarivo" . " le "; 
        $CI =& get_instance();
		$CI->load->model('demande_cred/edition_model');
        
        $data = $CI->edition_model->get_refus_data($idPret);
 	    $adresseAgence =  $data['agence'] . " le ";
    	$active_sheet->getCell('F9')->setValueExplicit($adresseAgence, $type);
        $active_sheet->getCell('I9')->setValueExplicit(date('d/m/Y'), $type);
        $active_sheet->getCell('H12')->setValueExplicit($data['nom_client'], $type);
        $active_sheet->getCell('H14')->setValueExplicit($data['li_adresse'], $type);
        $active_sheet->getCell('B16')->setValueExplicit($data['civilite'], $type);
        $active_sheet->getCell('B18')->setValueExplicit($data['nom_client'], $type);
        $active_sheet->getCell('F18')->setValueExplicit($data['prenom_client'], $type);
        $active_sheet->getCell('C19')->setValueExplicit($data['dt_pret'], $type);
        $active_sheet->getCell('B25')->setValueExplicit($data['civilite'], $type);
        $active_sheet->getCell('G27')->setValueExplicit($data['dt_pret'], $type);
        $active_sheet->getCell('J27')->setValueExplicit(number_format($data['mt_capital_emprunte'],2), $type);
        $active_sheet->getCell('D28')->setValueExplicit($data['nb_duree_mois'], $type);
        $active_sheet->getCell('E36')->setValueExplicit($data['civilite'],$type);
        $active_sheet->getCell('E49')->setValueExplicit($data['agence'],$type);
    	//Continuer le remplissage des fichiers selon le modèle  
    }
}

if (!function_exists('traitement_dbs_edition')) {
    function traitement_dbs_edition(&$active_sheet, $type, $idPret, $isCoEmprunteur = false) {
    	//VOIR LE MODELE INITIAL POUR REFERER AUX CHAMPS A REMPLIR
    	//CRA : 18/02/2016 : récupérer les données en utilisant $idPret
    	//Mettre le titre du document
    	$titre =  ($isCoEmprunteur) ? "IDENTIFICATION CLIENT (Co-Emprunteur)" : "IDENTIFICATION CLIENT (Emprunteur)";    	
    	$active_sheet->getCell('A7')->setValueExplicit($titre, $type);
		$data_pret = get_information_edition_by_id_pret($idPret,$isCoEmprunteur);
		
		
		
		$active_sheet->getCell('C8')->setValueExplicit(strtoupper($data_pret['li_nom']) , $type);
		$active_sheet->getCell('C9')->setValueExplicit($data_pret['li_prenom'] , $type);
		$active_sheet->getCell('C10')->setValueExplicit($data_pret['li_nom_jeune_fille'] , $type);
		$active_sheet->getCell('C11')->setValueExplicit($data_pret['li_adresse_domicile'] , $type);
		$active_sheet->getCell('C12')->setValueExplicit($data_pret['fonction_emp'], $type);
		$active_sheet->getCell('C13')->setValueExplicit($data_pret['dt_naissance'] , $type);
		
		$ide = explode(" ",$data_pret["identification"] );
		$active_sheet->getCell('A14')->setValueExplicit(!empty($data_pret['type_piece'])?$data_pret['type_piece']:$ide[0], $type);
		$active_sheet->getCell('C14')->setValueExplicit((!empty($data_pret['piece']) ? $data_pret['piece']:substr($data_pret['identification'],3)) , $type);
		
		$active_sheet->getCell('C15')->setValueExplicit($data_pret['dt_etablissement'] , $type);
		$active_sheet->getCell('C17')->setValueExplicit($data_pret['no_compte'] , $type);
		$active_sheet->getCell('C18')->setValueExplicit($data_pret['li_agence'] , $type);
		$active_sheet->getCell('L8')->setValueExplicit(number_format($data_pret['mt_capital_emprunte'],2,'.',' '), $type);
		$active_sheet->getCell('L9')->setValueExplicit($data_pret['nb_duree_mois'] , $type);
		
		
    	//Continuer le remplissage des fichiers selon le modèle 

		$decompte_prime = calcul_decompte_prime($data_pret);
		$assurance_taux = $data_pret["tx_assurance"];
		$active_sheet->getCell('C23')->setValueExplicit($assurance_taux , $type);
		$active_sheet->getCell('K23')->setValueExplicit(number_format($decompte_prime,2,'.',' ') , $type);	
		$lettre = day_en_lettre_edition();
		$active_sheet->getCell('L69')->setValueExplicit($lettre , $type);
    }
}

if (!function_exists('traitement_remboursement_edition')) {
    function traitement_remboursement_edition(&$active_sheet, $type, $idPret) {
    	//VOIR LE MODELE INITIAL POUR REFERER AUX CHAMPS A REMPLIR
    	//CRA : 18/02/2016 : récupérer les données en utilisant $idPret
    	
    	//Continuer le remplissage des fichiers selon le modèle            
        $CI =& get_instance();
		$CI->load->model('demande_cred/edition_model');
	    $data = $CI->edition_model->get_remboursement_data($idPret);
        $active_sheet->getCell('B11')->setValueExplicit($data['no_compte'], $type); 
        $active_sheet->getCell('F11')->setValueExplicit($data['cd_agence']. '- '. $data['li_agence'], $type);
        $active_sheet->getCell('K11')->setValueExplicit($data['gestionnaire_nom']. ' '.$data['gestionnaire_prenom'],$type);
   	 
    	$active_sheet->getCell('B15')->setValueExplicit($data['emprunt_nom'], $type);
    	$active_sheet->getCell('B16')->setValueExplicit($data['emprunt_prenom'], $type);
     	$active_sheet->getCell('B17')->setValueExplicit( $data['emprunt_dt_naiss'],$type);
        $active_sheet->getCell('E17')->setValueExplicit($data['emprunt_lieu_naiss'], $type);
        $active_sheet->getCell('B18')->setValueExplicit( $data['emprunt_adresse'] , $type);
        $active_sheet->getCell('B19')->setValueExplicit( $data['emprunt_tel'] , $type);
        $active_sheet->getCell('B20')->setValueExplicit( trim(str_replace('CIN','', $data['emprunt_no_piece_identite'])) , $type);
        $active_sheet->getCell('E20')->setValueExplicit( $data['emprunt_dt_piece_identite'] , $type);
        $active_sheet->getCell('B21')->setValueExplicit( $data['emprunt_lieu_piece_identite'] , $type);
        $active_sheet->getCell('B22')->setValueExplicit( $data['emprunt_profession'] , $type);
        $active_sheet->getCell('B23')->setValueExplicit( $data['emprunt_matricule'] , $type);
        
        $active_sheet->getCell('G15')->setValueExplicit($data['co_emprunt_nom'], $type);
    	$active_sheet->getCell('G16')->setValueExplicit($data['co_emprunt_prenom'], $type);
     	$active_sheet->getCell('G17')->setValueExplicit( $data['co_emprunt_dt_naiss'],$type);
        $active_sheet->getCell('J17')->setValueExplicit( $data['co_emprunt_lieu_naiss'], $type);
        $active_sheet->getCell('G18')->setValueExplicit( $data['co_emprunt_adresse'] , $type);
        $active_sheet->getCell('G19')->setValueExplicit( $data['co_emprunt_tel'] , $type);
        $active_sheet->getCell('G20')->setValueExplicit( trim(str_replace('CIN','', $data['co_emprunt_no_piece_identite'])) , $type);
        $active_sheet->getCell('J20')->setValueExplicit( $data['co_emprunt_dt_piece_identite'] , $type);
        $active_sheet->getCell('G21')->setValueExplicit( $data['co_emprunt_lieu_piece_identite'] , $type);
        $active_sheet->getCell('G22')->setValueExplicit( $data['co_emprunt_profession'] , $type);
        $active_sheet->getCell('G23')->setValueExplicit( $data['co_emprunt_matricule'] , $type);
        
        //exit();	
    }
}

if (!function_exists('personnal_remove_row')) {
    function personnal_remove_row(&$active_sheet, $debut, $nombre = 0) {    	
    	for ($i=0; $i<=$nombre; $i++) {
    		$row = $debut + $i;
    		$active_sheet->getRowDimension($row)->setRowHeight(0);
    		$active_sheet->getRowDimension($row)->setVisible(false);
    	}  	
    }
}
if(!function_exists("personnal_remove_width")){
	function personnal_remove_width(){
	}
}

if (!function_exists('traitement_demande_edition')) {
    function traitement_demande_edition(&$active_sheet, $type, $idPret, $isCoEmprunteur = false,$signature = "") {
    	//VOIR LE MODELE INITIAL POUR REFERER AUX CHAMPS A REMPLIR
    	//CRA : 23/02/2016 : récupérer les données en utilisant $idPret
    	//Vérifier si le prêt est différé oui ou non
    	$isDiffere = false ;    

		$data_pret = get_information_edition_by_id_pret($idPret);
		
		$montant_avec_virgule = explode(".",$data_pret['mt_capital_emprunte']);
		$m1 = $montant_avec_virgule[0];
		$mont_en_lettre =conversion_en_lettre($m1 );
		if(!empty($montant_avec_virgule[1])){
			$m2 = substr($montant_avec_virgule[1],0,2);
			
			$m_2 = conversion_en_lettre($m2 );
			$mont_en_lettre .= "virgule ".$m_2;
		}
		
		
		$active_sheet->getCell('A12')->setValueExplicit(strtoupper($mont_en_lettre." Ariary"),$type);
		
        if (!$isCoEmprunteur) {
    		//Si non Co-emprunteur , supprimer le pavé co-Emprunteur
    		//Si on supprime ce pavé, on fait comme cela 
    		//personnal_remove_row($active_sheet, 35, 18);	
			//$active_sheet->getRowDimension('W160')->setRowWidth(0);
			//$active_sheet->getRowDimension('W160')->setVisible(false);
    	}
		$sal_co = 0;
		$sal_autre = 0;
			
		if($data_pret["is_co_emprunteur"]==1 ){
			
		
				$data_pret_co = get_information_edition_by_id_pret($idPret,true);
				$co_emprunteur = $data_pret_co;
			
			
			
			$active_sheet->getCell('O37')->setValueExplicit($co_emprunteur["no_compte"], $type);
		
			$active_sheet->getCell('V37')->setValueExplicit($co_emprunteur["dt_ouverture"], $type);
			
			
		
			$active_sheet->getCell('O39')->setValueExplicit($co_emprunteur["li_nom"], $type);
			$active_sheet->getCell('O40')->setValueExplicit($co_emprunteur["li_prenom"], $type);
			
			$ide = explode(" ",$co_emprunteur["identification"] );
			
			
			$active_sheet->getCell('A43')->setValueExplicit(!empty($co_emprunteur['type_piece'])?$co_emprunteur['type_piece']:$ide[0], $type);
			$active_sheet->getCell('O43')->setValueExplicit((!empty($co_emprunteur['piece']) ? $co_emprunteur['piece']:substr($co_emprunteur['identification'],3)), $type);
			$active_sheet->getCell('T43')->setValueExplicit($co_emprunteur["dt_etablissement"], $type);
			$active_sheet->getCell('W43')->setValueExplicit($co_emprunteur["li_lieu_etablissement"], $type);
			$active_sheet->getCell('AB43')->setValueExplicit($co_emprunteur["li_lieu_duplicata"], $type);
		
			
			$active_sheet->getCell('O41')->setValueExplicit($co_emprunteur["dt_naissance"], $type);
			$active_sheet->getCell('U41')->setValueExplicit($co_emprunteur["li_lieu_naissance"], $type);
			$active_sheet->getCell('O42')->setValueExplicit($co_emprunteur["nationalite"], $type);
			$active_sheet->getCell('V42')->setValueExplicit($co_emprunteur["stat_marital"], $type);
			$active_sheet->getCell('VAB42')->setValueExplicit($co_emprunteur["nb_personnes_a_charge"], $type);
			$active_sheet->getCell('O44')->setValueExplicit($co_emprunteur["li_adresse_domicile"], $type);
			
			$active_sheet->getCell('O45')->setValueExplicit($co_emprunteur["li_telephone_domicile"],$type);
			$active_sheet->getCell('T46')->setValueExplicit($co_emprunteur["li_fax_domicile"],$type);
			$active_sheet->getCell('Y46')->setValueExplicit($co_emprunteur["li_email"],$type);
			
		
		$sal_co = $co_emprunteur['mt_salaire_net'];
		$sal_autre =$co_emprunteur["mt_autres_revenus_net"];
			
			$active_sheet->getCell('O47')->setValueExplicit($co_emprunteur["li_employeur"], $type);
			$active_sheet->getCell('O48')->setValueExplicit($co_emprunteur["emp_adresse"], $type);
			
			$active_sheet->getCell('O49')->setValueExplicit($co_emprunteur["fonction_emp"],$type);
			
			$active_sheet->getCell('O50')->setValueExplicit($co_emprunteur["nature_contrat_emprunteur"],$type);
			$active_sheet->getCell('O51')->setValueExplicit(number_format($co_emprunteur["mt_salaire_net"],2,'.',' '), $type);
			$active_sheet->getCell('AA50')->setValueExplicit($co_emprunteur["duree_contrat_co"], $type);
			
			$active_sheet->getCell('X54')->setValueExplicit($co_emprunteur["li_autres_sources_revenus"], $type);
			$active_sheet->getCell('X56')->setValueExplicit(number_format($co_emprunteur["mt_autres_revenus_net"],2,'.',' '), $type);
			
			
			$active_sheet->getCell('W160')->setValueExplicit("Signature de  Co-emprunteur",$type);
			$active_sheet->getCell('V165')->setValueExplicit($co_emprunteur["li_nom"]." ".$co_emprunteur["li_prenom"] ,$type);
			
			
		}
    	    	
    	if (!$isDiffere) {
    		//Supprimer A104 à A111
    		personnal_remove_row($active_sheet, 104, 7);
    	} else {
    		//Supprimer A112 à A120
    		personnal_remove_row($active_sheet, 112, 9);
    	}
    	
    	//Continuer le remplissage des fichiers selon le modèle
    	// emprunteur 

		
		$active_sheet->getCell('O18')->setValueExplicit($data_pret["no_compte"], $type);
		$active_sheet->getCell('W11')->setValueExplicit(number_format($data_pret["mt_capital_emprunte"],2,'.',' '), $type);
		$active_sheet->getCell('V18')->setValueExplicit($data_pret["dt_ouverture"], $type);
		
		
		
		$active_sheet->getCell('O20')->setValueExplicit($data_pret["li_nom"], $type);
		$active_sheet->getCell('O21')->setValueExplicit($data_pret["li_prenom"], $type);
		$active_sheet->getCell('O22')->setValueExplicit($data_pret["dt_naissance"], $type);
		$active_sheet->getCell('U22')->setValueExplicit($data_pret["li_lieu_naissance"], $type);
		
		
		$active_sheet->getCell('O23')->setValueExplicit($data_pret["nationalite"], $type);
		$active_sheet->getCell('V23')->setValueExplicit($data_pret["stat_marital"], $type);
		
		
		$active_sheet->getCell('AB23')->setValueExplicit($data_pret["nb_personnes_a_charge"], $type);
		$ide = explode(" ",$data_pret["identification"] );
		
		
		$active_sheet->getCell('A24')->setValueExplicit($ide[0], $type);
		$active_sheet->getCell('O24')->setValueExplicit(substr($data_pret['identification'],3), $type);
		$active_sheet->getCell('T24')->setValueExplicit($data_pret["dt_etablissement"], $type);
		$active_sheet->getCell('W24')->setValueExplicit($data_pret["li_lieu_etablissement"], $type);
		$active_sheet->getCell('AB24')->setValueExplicit($data_pret["li_lieu_duplicata"], $type);
		
		
		$active_sheet->getCell('O25')->setValueExplicit($data_pret["li_adresse_domicile"], $type);
		
		$active_sheet->getCell('O26')->setValueExplicit($data_pret["li_telephone_domicile"], $type);
		$active_sheet->getCell('T27')->setValueExplicit($data_pret["li_fax_domicile"], $type);
		$active_sheet->getCell('T26')->setValueExplicit($data_pret["li_email"], $type);
		$active_sheet->getCell('Y27')->setValueExplicit($data_pret["li_email"], $type);
		$active_sheet->getCell('O27')->setValueExplicit(substr($data_pret["li_telephone_portable"],3), $type);
		$active_sheet->getCell('O28')->setValueExplicit($data_pret["li_employeur"], $type);
		$active_sheet->getCell('O29')->setValueExplicit($data_pret["li_adresse_domicile"], $type);
		$active_sheet->getCell('O30')->setValueExplicit($data_pret["fonction_emp"],$type);
		$active_sheet->getCell('O31')->setValueExplicit($data_pret["nature_contrat_emprunteur"], $type);
		$active_sheet->getCell('O32')->setValueExplicit(number_format($data_pret["mt_salaire_net"],2,'.',' '), $type);
		$active_sheet->getCell('AA31')->setValueExplicit($data_pret["enceinnete_ans"], $type);
		// autres valeurs revenues
		$active_sheet->getCell('O54')->setValueExplicit($data_pret["li_autres_sources_revenus"], $type);
		$active_sheet->getCell('O56')->setValueExplicit(number_format($data_pret["mt_autres_revenus_net"],2,'.',' '), $type);
		
		$lettre = day_en_lettre_edition();
		$active_sheet->getCell('V158')->setValueExplicit($lettre , $type);
		
		
		
		
		
	
		
		
		/*$active_sheet->getCell('Z51')->setValueExplicit(number_format($data_pret["mt_salaire_net"],2,'.',' '), $type);
		$active_sheet->getCell('Z52')->setValueExplicit(number_format($data_pret["mt_autres_revenus_net"],2,'.',' '), $type);*/
		
		//pret condition 
		
		$active_sheet->getCell('E64')->setValueExplicit($data_pret["objet_demande"], $type);
		
		$sal_co = $sal_co +  $data_pret['mt_salaire_net'];
		$sal_autre = $sal_autre+ $data_pret["mt_autres_revenus_net"];
		$active_sheet->getCell('E67')->setValueExplicit(number_format($data_pret["mt_capital_emprunte"],2,'.',' '), $type);
		$active_sheet->getCell('X67')->setValueExplicit(number_format($sal_co,2,'.',' '), $type);
		$active_sheet->getCell('X68')->setValueExplicit(number_format($sal_autre ,2,'.',' '), $type);
	
		
		$as = 0;
		if(!empty($data_pret["tx_assurance"])){
			$assurance = explode("%",$data_pret["tx_assurance"]);
			$as = $assurance[0];
		 }
		$t = (($data_pret["tx_annuel_ht"]+ $data_pret["tva"] + $as)/100);
		$mensualite = calcul_mensualite_pret($data_pret["mt_capital_emprunte"], $data_pret["nb_duree_mois"], $t);
		$active_sheet->getCell('X69')->setValueExplicit(number_format($mensualite,2,'.',' '), $type);
		
		
		
		$fin_contrat = date_apres_n_mois($data_pret["dt_debut_pret"],$data_pret["nb_duree_mois"]);
		
		$active_sheet->getCell('B55')->setValueExplicit($data_pret["dt_debut_pret"], $type);
		$active_sheet->getCell('E55')->setValueExplicit($fin_contrat, $type);
		$active_sheet->getCell('C40')->setValueExplicit($data_pret["li_autres_sources_revenus"], $type);
		$active_sheet->getCell('C41')->setValueExplicit(number_format($data_pret["mt_autres_revenus_net"],2,'.',' '), $type);
		
		$active_sheet->getCell('X57')->setValueExplicit(number_format($data_pret["montant_garantie"],2,'.',' '), $type);
		$as = 0;
		if(!empty($data_pret["tx_assurance"])){
			$assurance = explode("%",$data_pret["tx_assurance"]);
			$as = $assurance[0];
		 }
		$t = (($data_pret["tx_annuel_ht"]+ $data_pret["tva"] + $as)/100);
		$mensualite = calcul_mensualite_pret($data_pret["mt_capital_emprunte"], $data_pret["nb_duree_mois"], $t);
		$active_sheet->getCell('Z54')->setValueExplicit(number_format($mensualite,2,'.',' '), $type);
		$active_sheet->getCell('E69')->setValueExplicit(number_format($data_pret["tx_annuel_ht"],2,'.',' '), $type);
		$active_sheet->getCell('E70')->setValueExplicit($data_pret["tx_assurance"], $type);
		$active_sheet->getCell('L71')->setValueExplicit($data_pret["nb_duree_mois"], $type);
		
		$frais_dossier = calculate_frais_dossier_finale($data_pret);
		$active_sheet->getCell('E72')->setValueExplicit(number_format($frais_dossier,2,'.',' '), $type);
		$active_sheet->getCell('K75')->setValueExplicit($data_pret["li_garantie"], $type);
		
		/* engagement encours */
		if(sizeof($data_pret["liste_engagement_cours"]) > 0){
			$encours = $data_pret["liste_engagement_cours"];
			$active_sheet->getCell('A81')->setValueExplicit($encours[0]["objet_demande"], $type);
			$active_sheet->getCell('P81')->setValueExplicit("BNI Madagascar", $type);
			$active_sheet->getCell('U81')->setValueExplicit(number_format($encours[0]["mt_capital_emprunte"],2,'.',' '), $type);
			$active_sheet->getCell('X81')->setValueExplicit($encours[0]["nb_duree_mois"], $type);
			$active_sheet->getCell('Z81')->setValueExplicit($encours[0]["en_cours"], $type);
			if(!empty($encours[1])){
				$active_sheet->getCell('A82')->setValueExplicit($encours[1]["objet_demande"], $type);
				$active_sheet->getCell('P82')->setValueExplicit(number_format($encours[1]["mt_capital_emprunte"],2,'.',' '), $type);
				$active_sheet->getCell('P82')->setValueExplicit("BNI Madagascar", $type);
				$active_sheet->getCell('X82')->setValueExplicit($encours[1]["nb_duree_mois"], $type);
				$active_sheet->getCell('Z82')->setValueExplicit($encours[1]["en_cours"], $type);
					
			}
		}
		
		/*dernier remplissage */
		$duree_lettre = conversion_en_lettre($data_pret["nb_duree_mois"]);
		
		$active_sheet->getCell('A113')->setValueExplicit("- ".$data_pret["nb_duree_mois"]." (".$duree_lettre.")", $type);
		
		$active_sheet->getCell('O113')->setValueExplicit($data_pret["tx_annuel_ht"]." % ",$type);
		
		$active_sheet->getCell('A114')->setValueExplicit("- et ".$data_pret["nb_duree_mois"],$type);
		$active_sheet->getCell('E114')->setValueExplicit($duree_lettre,$type);
		$active_sheet->getCell('W114')->setValueExplicit(number_format($mensualite,2,'.',' '),$type);
		$montant_avec_virgule = explode(".",$mensualite);
		$m1 = $montant_avec_virgule[0];
		$mont_en_lettre =conversion_en_lettre($m1 );
		if(!empty($montant_avec_virgule[1])){
			$m2 = substr($montant_avec_virgule[1],0,2);
			$m_2 = conversion_en_lettre($m2 );
			$mont_en_lettre .= "virgule ".$m_2;
		}
		
		
		$active_sheet->getCell('A115')->setValueExplicit(strtoupper($mont_en_lettre),$type);
		
		
		$fin_contrat = date_apres_n_mois($data_pret["dt_debut_pret"],$data_pret["nb_duree_mois"]);
		$active_sheet->getCell('I116')->setValueExplicit($data_pret["dt_debut_pret"],$type);
		$active_sheet->getCell('Q116')->setValueExplicit($fin_contrat,$type);
		
		$active_sheet->getCell('V118')->setValueExplicit($data_pret["no_compte"], $type);
		$active_sheet->getCell('Y149')->setValueExplicit($data_pret["no_compte"], $type);
		$active_sheet->getCell('O155')->setValueExplicit("LE DIRECTEUR D'AGENCE", $type);
		$active_sheet->getCell('J155')->setValueExplicit("Monsieur", $type);
		$active_sheet->getCell('S169')->setValueExplicit($signature , $type);
		
		
		
		
		
		
		
    }
}
if(!function_exists("if_exist_caution")){
	function if_exist_caution($id_pret){
		$criteres = array("id_pret"=>$id_pret,
			"type"=>35
		);
		$isExist = if_garanties_exist_type($criteres);
		return $isExist;
	}
}
if(!function_exists("if_nantissement_acquerir")){
	function if_nantissement_acquerir($id_pret){
		$criteres = array("id_pret"=>$id_pret,
			"type"=>36,
			"table_join"=>"garantie_nantissement",
			"acquisition"=>48
		);
		$isExist = if_garanties_exist_type($criteres);
		return $isExist;
	}
}
if(!function_exists("if_nantissement")){
	function if_nantissement($id_pret){
		$criteres = array("id_pret"=>$id_pret,
			"type"=>36,
			"table_join"=>"garantie_nantissement",
			"acquisition"=>47
		);
		$isExist = if_garanties_exist_type($criteres);
		return $isExist;
	}
}
if(!function_exists("if_hypotheque")){
	function if_hypotheque($id_pret){
		$criteres = array("id_pret"=>$id_pret,
			"type"=>38,
			"table_join"=>"garantie_hypotheque",
			"acquisition"=>47
		);
		$isExist = if_garanties_exist_type($criteres);
		return $isExist;
	}
}
if(!function_exists("if_hypotheque_acquerir")){
	function if_hypotheque_acquerir($id_pret){
		$criteres = array("id_pret"=>$id_pret,
			"type"=>38,
			"table_join"=>"garantie_hypotheque",
			"acquisition"=>48
		);
		$isExist = if_garanties_exist_type($criteres);
		return $isExist;
	}
}
if(!function_exists("if_garanties_exist_type")){
	function if_garanties_exist_type($criteres){
		$isExist = false;
		$CI =& get_instance();
		$CI->load->model('garantie/pret_garantie_model','garantie');
		$test = $CI->garantie->get_garantis_exists($criteres);
		if($test>0){
			$isExist = true;
		}
		return $isExist;
	}
}
if(!function_exists("get_garantie_by_type")){
	function get_garantie_by_type($criteres){
		$CI =& get_instance();
		$CI->load->model('garantie/pret_garantie_model','garantie');
		$criteres["donnee"] = true;
		$garantie = $CI->garantie->get_garantis_exists($criteres);
		return $garantie[0];
	}
}

if (!function_exists('test_edition')){
	 function test_edition(&$active_sheet, $type, $idPret, $isCoEmprunteur = false,$signature="") {
			personnal_remove_row($active_sheet, 5, 50);	
	 }
}

if(!function_exists("get_date_en_lettre")){
	function get_date_en_lettre(){
		setlocale(LC_TIME, 'french');
		$date = strftime('%A %d %B %Y', time());
		return $date;
	}
}
if (!function_exists('traitement_coc_edition')) {
    function traitement_coc_edition(&$active_sheet, $type, $idPret, $isCoEmprunteur = false,$signature="") {
    	//VOIR LE MODELE INITIAL POUR REFERER AUX CHAMPS A REMPLIR
    	//CRA : 25/02/2016 : récupérer les données en utilisant $idPret	
    	//Vérifier tous ces informations pour construire le COC dinamiquement
		
		/* MAMP GET DONNEES BY PRET */
		 
		 

		$data_pret = get_information_edition_by_id_pret($idPret,$isCoEmprunteur);
		
		
		/*info titre co_emprunture ou emprunteur */
		$qualite = $data_pret["qualite"];
		$a36 = "Ci-après dénommé ";
		$adebiteir = "Débiteur d'autre part,";
		$domicile = "domicilié au ";
		if($qualite!="Monsieur"){
			$a36 = "Ci-après dénommée ";
			$adebiteir = "Débitrice d'autre part,";
			$domicile = "domiciliée au ";
		}
		$Le_les_emprunteur = "L'emprunteur";
		$acceptent = " accepte qu’il lui ";
		$a36.=" l’emprunteur, ";
		if($data_pret['is_co_emprunteur']==1){
			$acceptent = " acceptent qu’il leur ";
			$Le_les_emprunteur = "Les emprunteurs";
			$isCoEmprunteur = true;
			$co_emprunteur = get_information_edition_by_id_pret($idPret,true);
			
			
				$a36 = "Ci-après dénommés les emprunteurs,";
				$adebiteir = "Débiteurs, d’autre part.";
				$co_titre  = " - ". $co_emprunteur["qualite"]." ". strtoupper($co_emprunteur["li_nom"])." " .$co_emprunteur["li_prenom"] .", ".$co_emprunteur["fonction_emp"]." auprès de ".$co_emprunteur['li_employeur']." , ";
				$ne_le_co =" né/née le ".$co_emprunteur["dt_naissance"] ." à ".$co_emprunteur["li_lieu_naissance"].", ";
				$info_parent_co = " fils/fille de ".$co_emprunteur["li_nom_pere"]." et de ".$co_emprunteur["li_nom_mere"];
				$cin_num_co = substr($data_pret['identification'],3);
				$qual_co = $co_emprunteur["qualite"];
				$domicile_co = "domicilié au ";
				if($qual_co !="Monsieur"){
					$domicile_co = "domiciliée au ";
					
				}
				
				
				/*ecriture comp_ emprunteur */
				
				
		}
		
		$emprunteur  = " - ". $data_pret["qualite"]." ". strtoupper($data_pret["li_nom"])." " .$data_pret["li_prenom"] .", ".$data_pret["fonction_emp"]." auprès de ".$data_pret['li_employeur']." ,";
		$ne_le = "né/née le ".$data_pret["dt_naissance"] ." à ".$data_pret["li_lieu_naissance"].", ";
		$info_parent = " fils/fille de ".$data_pret["li_nom_pere"]." et de ".$data_pret["li_nom_mere"];
		
		$cin_num = substr($data_pret['identification'],3);
		
		
		$compte_exemplaire = 3;
		
		
    	$isCPS = if_exist_caution($idPret);
		
		//* verifier caution *//
    	$isNantissementAcquerir = if_nantissement_acquerir($idPret); //Etat d'acquisition ==> Si acquérir, c'est à acquerir 
    	$isNantissement = if_nantissement($idPret);//Etat d'acquisition ==> Si différent acquérir, c'est à acquerir
    	$isPromesseHypotheque = if_hypotheque_acquerir($idPret);//Etat d'acquisition ==> Si acquérir, c'est une promesse 
    	$isHypotheque = if_hypotheque($idPret); // Etat d'acquisition ==> Si différent de acquérir, c'est une hypothèque   	
    	

	
	//var_dump($isNantissementAcquerir);
		
		
        //A10 et E287: A remplacer par la valeur saisie 
       	
    	if (!$isCoEmprunteur) {
    		//Si non Co-emprunteur , supprimer le pavé co-Emprunteur	
    		personnal_remove_row($active_sheet, 26, 11);		
    	}else{
			//ecriture  co-emprunteur
			
			$active_sheet->getCell('B28')->setValueExplicit($co_titre, $type); 
			$active_sheet->getCell('A29')->setValueExplicit($ne_le_co, $type); 
			$active_sheet->getCell('A30')->setValueExplicit($info_parent_co , $type); 
			$active_sheet->getCell('F31')->setValueExplicit($cin_num_co, $type); 
			$active_sheet->getCell('H31')->setValueExplicit("délivrée le ".$co_emprunteur['dt_etablissement'], $type); 
			$active_sheet->getCell('J31')->setValueExplicit(", à ".$co_emprunteur['li_lieu_etablissement'], $type); 
			$domicile_co .= $co_emprunteur['li_adresse_domicile'];
			$active_sheet->getCell('A34')->setValueExplicit($domicile_co , $type); 
			
		}
    	
    	//Pour A36
    	//Si 1AVG : Ci-après =SI(TITRE!="Monsieur";"dénommé";"dénomée")  l’emprunteur,
    	//Si 2AVG : Ci-après dénommés les emprunteurs,
    	//Pour H38: 
    	//Si 1AVG :=SI(TITRE!="Monsieur";"Débiteur d'autre part,";"Débitrice d'autre part,")
    	//Si 2AVG : Débiteurs, d’autre part.
    	    	  	    	
    	if (!$isCPS) {
    		//Supprimer Cautionnement
    		personnal_remove_row($active_sheet, 170, 12);
    	}
		else{
			$criteres_caution = array("id_pret"=>$idPret,
			"type"=>35);
			$caution = get_garantie_by_type($criteres_caution);
			$active_sheet->getCell('A173')->setValueExplicit($caution["li_garantie"], $type);  
			/* remplir  */
		}
    	if (!$isNantissementAcquerir) {
    		//Supprimer Nantissement de matériel à acquérir
    		personnal_remove_row($active_sheet, 183,16 );
    	}
		else{
			$criteres_nantisse = array("id_pret"=>$idPret,
				"type"=>36,
				"table_join"=>"garantie_nantissement",
				"acquisition"=>48
			);
			$nantissement_ac = get_garantie_by_type($criteres_nantisse);	
			$active_sheet->getCell('A188')->setValueExplicit(" .01 véhicule de marque ".$nantissement_ac["li_marque"]." , type".$nantissement_ac["li_type_voiture"]." numéro dans la série du type ".$nantissement_ac["num_serie"]." ,", $type);  
			$active_sheet->getCell('A189')->setValueExplicit(" immatriculation ". $nantissement_ac["immatriculation"]." ". $nantissement_ac["nb_place"]." places, puissance ".$nantissement_ac["li_puissance"]." CV , énergie ".$nantissement_ac["li_energie"] ,$type);
		}
    	if (!$isNantissement) {
    		//Supprimer Nantissement de matériel et Assurance incendie/vol
    		personnal_remove_row($active_sheet, 201, 14);
    	}
		else{
			$criteres_nantisse = array("id_pret"=>$idPret,
				"type"=>36,
				"table_join"=>"garantie_nantissement",
				"acquisition"=>47
			);
			$nantissement = get_garantie_by_type($criteres_nantisse);
			 
			
				$active_sheet->getCell('A203')->setValueExplicit($data_pret["qualite"]." ". strtoupper($data_pret["li_nom"])." " .$data_pret["li_prenom"], $type); 
				
				/**/
				$active_sheet->getCell('A205')->setValueExplicit(" .Un  véhicule de marque ".$nantissement["li_marque"]." , type".$nantissement["li_type_voiture"]." numéro dans la série du type ".$nantissement["num_serie"]." ,", $type); 
				$active_sheet->getCell('A206')->setValueExplicit("puissance ".$nantissement["li_puissance"]." CV , numéro d’immatriculation ".$nantissement["immatriculation"] ,$type);
		}
    	if (!$isHypotheque) {
	    	//Supprimer Affectation hypothécaire et Assurance incendie :
	    	personnal_remove_row($active_sheet, 222, 16);
			personnal_remove_row($active_sheet, 268, 1);
    	}
		else{
		
			$criteres = array("id_pret"=>$idPret,
				"type"=>38,
				"table_join"=>"garantie_hypotheque",
				"acquisition"=>47
			);
		
			$hypotheque = get_garantie_by_type($criteres);
			
			$active_sheet->getCell('A223')->setValueExplicit($data_pret["qualite"]." ". strtoupper($data_pret["li_nom"])." " .$data_pret["li_prenom"], $type); 
			
			$active_sheet->getCell('A225')->setValueExplicit(" - ".strtoupper($hypotheque["li_nom_proprietaire"])." TF N° ".$hypotheque["li_titre_foncier"]." d’une contenance de ".$hypotheque["li_superficie"]." ,sise à ".$hypotheque["li_localisation"], $type);  
			
		}
    	if (!$isPromesseHypotheque) {
    		//Supprimer Promesse d’affectation hypothécaire
    		personnal_remove_row($active_sheet, 239, 14);
    	}
		else{
			$criteres = array("id_pret"=>$idPret,
				"type"=>38,
				"table_join"=>"garantie_hypotheque",
				"acquisition"=>48
			);
		
			$hypotheque_pro= get_garantie_by_type($criteres);
			$active_sheet->getCell('A240')->setValueExplicit($data_pret["qualite"]." ". strtoupper($data_pret["li_nom"])." " .$data_pret["li_prenom"], $type); 
			$active_sheet->getCell('A243')->setValueExplicit(strtoupper($hypotheque_pro["li_nom_proprietaire"])." TF N° ".$hypotheque_pro["li_titre_foncier"]." d’une contenance de ".$hypotheque_pro["li_superficie"]." ,sise à ".$hypotheque_pro["li_localisation"], $type);  
			
		}
    	
    	//Nombre d'exemplaire 
    	//G264 : Fait en QUATRE exemplaires originaux, dont :
    	//A265 = - DEUX pour la BNI  MADAGASCAR ==> 2 = obligatoire
		//A266 = - UN   pour les emprunteurs ==> 1 = obligatoire
		//Supprimer ceux qui sont inutiles
		//A267 = - UN pour l’enregistrement ==> 1 = Nantissement, Hypothèque, Promesse d'hypothèque
		//A268 = - UN pour le notaire ==> 1 = Hypothèque, 
		//A269 = - UN pour le Centre Immatriculateur ==> 1 = Nantissement,
		//A270 = - UN pour le Greffe du Tribunal ==> 1 = Nantissement,
		
		$tet = 0;
		$teh= 0;
		$nat = 0;
		if($isNantissement || $isPromesseHypotheque || $isHypotheque  ){
			$tet= 1;
			$active_sheet->getCell('A267')->setValueExplicit("- UN pour l’enregistrement", $type);  
			
		}
		if($isHypotheque){
			$teh= 1;
			$active_sheet->getCell('A268')->setValueExplicit("- UN pour le notaire", $type); 
		}
		if($isNantissement){
			$nat = 2;
			
			$active_sheet->getCell('A269')->setValueExplicit("- UN pour le Centre Immatriculateur", $type); 
			$active_sheet->getCell('A270')->setValueExplicit("- UN pour le Greffe du Tribunal", $type); 
		}
		if(!$isNantissement && !$isPromesseHypotheque && !$isHypotheque  ){
			personnal_remove_row($active_sheet, 267, 1);
		}
		$chiffre=array(1=>"un",2=>"deux",3=>"trois",4=>"quatre",5=>"cinq",6=>"six",7=>"sept",8=>"huit",9=>"neuf",10=>"dix",11=>"onze",12=>"douze",13=>"treize",14=>"quatorze",15=>"quinze",16=>"seize",17=>"dix-sept",18=>"dix-huit",19=>"dix-neuf",20=>"vingt",30=>"trente",40=>"quarante",50=>"cinquante",60=>"soixante",70=>"soixante-dix",80=>"quatre-vingt",90=>"quatre-vingt-dix");
		$compte_exemplaire = $compte_exemplaire+ ($nat+$teh+$tet);
		
		$exemp_l = $chiffre[$compte_exemplaire];
		
		
		$titre_G264 =  "Fait en ".strtoupper($exemp_l)." exemplaires originaux, dont : ";
		
		$active_sheet->getCell('G264')->setValueExplicit($titre_G264, $type);  
		
		if($nat==0){
			personnal_remove_row($active_sheet, 269, 2);
		}
		
		
		
		
		
		
    	//Si on supprime '- UN pour le Greffe du Tribunal
    	
		
    	//Signature
    	//E291 : L’emprunteur ou Les emprunteurs si co-Emprunteur
    	if (!$isCoEmprunteur) {
    		personnal_remove_row($active_sheet, 303, 9);
    	}
    	//Lu et approuvé
    	//C315 : Signature du client à faire précéder de la mention manuscrite « Lu et approuvé »
    	// ou Signature des emprunteurs à faire précéder de la mention manuscrite « Lu et approuvé »
    	//si co-emprunteur
    	
    	//Continuer le remplissage des fichiers selon le modèle
    	$active_sheet->getCell('A10')->setValueExplicit($signature, $type);  
		$active_sheet->getCell('B19')->setValueExplicit($emprunteur, $type); 
		$active_sheet->getCell('A20')->setValueExplicit($ne_le, $type); 
		$active_sheet->getCell('A21')->setValueExplicit($info_parent, $type); 
		$active_sheet->getCell('F22')->setValueExplicit($cin_num, $type); 
		$active_sheet->getCell('H22')->setValueExplicit("délivrée le ".$data_pret['dt_etablissement'], $type); 
		$active_sheet->getCell('J22')->setValueExplicit(", à ".$data_pret['li_lieu_etablissement'], $type); 
		$domicile .= $data_pret['li_adresse_domicile'];
		$active_sheet->getCell('A23')->setValueExplicit($domicile , $type); 
		$active_sheet->getCell('H38')->setValueExplicit($adebiteir , $type); 
		
		/* nom  emp */
		$debut_deblocage = "La BNI MADAGASCAR ouvre à "; 
		$debut_deblocage .= $data_pret["qualite"]." ". strtoupper($data_pret["li_nom"])." " .$data_pret["li_prenom"];
		if($isCoEmprunteur){
			$debut_deblocage .= " et ".$co_emprunteur["qualite"]." ". strtoupper($co_emprunteur["li_nom"])." " .$co_emprunteur["li_prenom"];
		}
		$active_sheet->getCell('A48')->setValueExplicit($debut_deblocage , $type); 
		
		$montant = " de MGA ";
		//$lettre_montant = conversion_en_lettre($data_pret["mt_capital_emprunte"]);
		
		$montant_avec_virgule = explode(".",$data_pret["mt_capital_emprunte"]);
		$m1 = $montant_avec_virgule[0];
		$lettre_montant =conversion_en_lettre($m1 );
		if(!empty($montant_avec_virgule[1])){
			$m2 = substr($montant_avec_virgule[1],0,2);
			$m_2 = conversion_en_lettre($m2 );
			$lettre_montant.= "virgule ".$m_2;
		}
		
		
		$montant_format = number_format($data_pret["mt_capital_emprunte"], 2,"."," ");
		
		$montant .= $montant_format;
		$montant .= " (".strtoupper($lettre_montant)." ARIARY)";
		$active_sheet->getCell('C50')->setValueExplicit($montant , $type); 
		$active_sheet->getCell('A51')->setValueExplicit(" pour ".$data_pret["objet_demande"] , $type); 
		
		/* taux d'interet */
		$taux = explode(".",$data_pret['tx_annuel_ht']);
		$taux_l = conversion_en_lettre($taux[0]);
		if(!empty($taux[1])){
			$taux_l.="virgule ";
			$apres = conversion_en_lettre($taux[1]);
			$taux_l.=$apres;
		}
		$active_sheet->getCell('A61')->setValueExplicit(" Le taux d'intérêts appliqué à cette opération est  de ".$data_pret['tx_annuel_ht']." % (".strtoupper($taux_l)." POUR CENT)" , $type); 
		
		/* remboursement */
		
		$as = 0;
		if(!empty($data_pret["tx_assurance"])){
			$assurance = explode("%",$data_pret["tx_assurance"]);
			$as = $assurance[0];
		 }
		$t = ($data_pret["tx_annuel_ht"]+ $data_pret["tva"] + $as)/100;
		$mois_en_lettre = conversion_en_lettre($data_pret["nb_duree_mois"]);
		$active_sheet->getCell('I80')->setValueExplicit($data_pret["nb_duree_mois"]. " (".strtoupper($mois_en_lettre)." MOIS)", $type);
		$mensualite = calcul_mensualite_pret($data_pret["mt_capital_emprunte"], $data_pret["nb_duree_mois"], $t);
		$active_sheet->getCell('E81')->setValueExplicit(number_format($mensualite,2,'.',' '), $type);
		
		$montant_avec_virgule = explode(".",$mensualite);
		$m1 = $montant_avec_virgule[0];
		$lettre_montant_mens =conversion_en_lettre($m1 );
		if(!empty($montant_avec_virgule[1])){
			$m2 = substr($montant_avec_virgule[1],0,2);
			$m_2 = conversion_en_lettre($m2 );
			$lettre_montant_mens.= "virgule ".$m_2;
		}
		
		
		$active_sheet->getCell('A82')->setValueExplicit(strtoupper($lettre_montant_mens) ." ARIARY ", $type);
		
		$fin_contrat = date_apres_n_mois($data_pret["dt_debut_pret"],$data_pret["nb_duree_mois"]);
		
		$active_sheet->getCell('C83')->setValueExplicit($data_pret["dt_debut_pret"], $type);
		$active_sheet->getCell('E83')->setValueExplicit($fin_contrat, $type);
		
		$active_sheet->getCell('A87')->setValueExplicit($data_pret["no_compte"], $type);
		$active_sheet->getCell('A92')->setValueExplicit("Toutefois, au cas où la date de réception du virement reçu de ".$data_pret['li_employeur'] , $type);
		
		/*garantie */
		//Assurance vie-groupe
		
		
		$emp = $data_pret["qualite"]." ". strtoupper($data_pret["li_nom"])." " .$data_pret["li_prenom"];
		$emp_et_co = $emp;
		if($isCoEmprunteur){
			$emp_et_co .= " et ";
			$co_emp = $co_emprunteur["qualite"]." ". strtoupper($co_emprunteur["li_nom"])." " .$co_emprunteur["li_prenom"];
			$emp_et_co .= $co_emp;
		}
		$emp_et_co .= " ".$acceptent;
		$active_sheet->getCell('A159')->setValueExplicit($emp_et_co, $type);
		
		/* election domicile */
		
		$active_sheet->getCell('A257')->setValueExplicit("- ".$emp_et_co." à leur domicile cité ci-dessus", $type);
		
		/* signateur */
		
		
		$active_sheet->getCell('E291')->setValueExplicit($Le_les_emprunteur, $type);
		$active_sheet->getCell('E291')->setValueExplicit($Le_les_emprunteur, $type);
		$active_sheet->getCell('E302')->setValueExplicit($emp, $type);
		if($isCoEmprunteur){
			$active_sheet->getCell('E310')->setValueExplicit($co_emp , $type);
		}
		
		$lettre = day_en_lettre_edition();
		$active_sheet->getCell('H272')->setValueExplicit($lettre , $type);
		
		
		
    }
}
if(!function_exists("get_tous_decision_by_pret")){
	function get_tous_decision_by_pret($id_pret){
		$CI =& get_instance();
		$CI->load->model('workflow/pret_workflow_transition_model','historique');
		$criteres = array("id_pret"=>$id_pret);
		$data_historique = $CI->historique->get_historique_decision($criteres);
		return $data_historique;
	}
}
if(!function_exists("get_tous_reserve_anomalie")){
	function get_tous_reserve_anomalie($id_pret){
		$CI =& get_instance();
		$CI->load->model('avis_decision/pret_regularisation_model','regularisation');
		$data_reserve =  array();
		$criteres=array('id_pret'=>$id_pret,"group_by"=>"group_by");
		$data_reserve = $CI->regularisation->get_regularisation_pret($criteres);
		
		return $data_reserve;
	}
}
if(!function_exists("traitement_avis_decision")){
	function traitement_avis_decision(&$active_sheet,$type,$id_pret){
		$decision = get_tous_decision_by_pret($id_pret);
		$count = sizeof($decision);
		if($count>0){
			$active_sheet->getCell('E2')->setValueExplicit("Réf dossier : ".$decision[0]['ref_dossier'], $type);
		}
		for($i=0;$i<$count;$i++){
			$active_sheet->getCell('A'.($i+8))->setValueExplicit(strtoupper($decision[$i]['nom']), $type);
			$active_sheet->getCell('B'.($i+8))->setValueExplicit($decision[$i]['fonction'], $type);
			$active_sheet->getCell('C'.($i+8))->setValueExplicit($decision[$i]['date_heure'], $type);
			$active_sheet->getCell('D'.($i+8))->setValueExplicit(isset($decision[$i]['value'])? $decision[$i]['value']:" - ", $type);
			
			$active_sheet->getCell('E'.($i+8))->setValueExplicit(isset($decision[$i]['commentaire'])? $decision[$i]['commentaire'] :" - ", $type);
		}
	}
}
if(!function_exists("traitement_anomalie_reserve")){
	function traitement_anomalie_reserve(&$active_sheet,$type,$id_pret){
		$data_reserve = get_tous_reserve_anomalie($id_pret);
		$count = sizeof($data_reserve);
		if($count>0){
			$active_sheet->getCell('L2')->setValueExplicit("Réf dossier : ".$data_reserve[0]['ref_dossier'], $type);
		}
		for($i=0;$i<$count;$i++){
			$active_sheet->getCell('C'.($i+8))->setValueExplicit($data_reserve[$i]['code'], $type);
			$active_sheet->getCell('E'.($i+8))->setValueExplicit($data_reserve[$i]['value'], $type);
			$active_sheet->getCell('F'.($i+8))->setValueExplicit($data_reserve[$i]['dt_emission'], $type);
			$active_sheet->getCell('G'.($i+8))->setValueExplicit(isset($data_reserve[$i]['dt_regularisation'])? $data_reserve[$i]['dt_regularisation']:" - ", $type);
			
			$active_sheet->getCell('L'.($i+8))->setValueExplicit(isset($data_reserve[$i]['nom_user'])? strtoupper($data_reserve[$i]['nom_user'])." ".$data_reserve[$i]['prenom_user'] :" - ", $type);
			
		}
		
	}
}
if(!function_exists("generate_publipostage_exportation")){
	function generate_publipostage_exportation($CI,$id_pret,$exportation,$extension){
		$nomFichierModele =  $CI->config->item('dir_modele').$exportation . $extension;
		
		$CI->load->library('excel'); 
			
        $excel2 = PHPExcel_IOFactory::createReader('Excel2007');        
        $excel2 = $excel2->load($nomFichierModele);
		$type = PHPExcel_Cell_DataType::TYPE_STRING;
		$excel2->setActiveSheetIndex(0);
		$active_sheet= $excel2->getActiveSheet();
		switch($exportation){
			case "deblocage":
				traitement_exportation_deblocage($active_sheet, $type, $id_pret);
			break;
			case "anomalie_reserve":
				traitement_anomalie_reserve($active_sheet,$type,$id_pret);
			break;
			case "avis_decision":
				traitement_avis_decision($active_sheet,$type,$id_pret);
			break;
		}
		$now = new DateTime("now");
		$nom_fichier = $CI->config->item('dir_pdf') . "exportation_".$now->format("Y-m-d_H-i-s");
		$objWriter = PHPExcel_IOFactory::createWriter($excel2, 'Excel2007');
		$objWriter->save( $nom_fichier . $extension);
		return $nom_fichier;	            
	}

}
if (!function_exists('generate_publipostage_edition')) {
    function generate_publipostage_edition($CI, $idPret,  $edition, $isCoEmprunteur = false, $extension,$signature="") {
    	$nomFichierModele =  $CI->config->item('dir_modele').$edition . $extension;    	
        
        $CI->load->library('excel'); 
			
        $excel2 = PHPExcel_IOFactory::createReader('Excel2007');        
        $excel2 = $excel2->load($nomFichierModele);
		$type = PHPExcel_Cell_DataType::TYPE_STRING;
		$excel2->setActiveSheetIndex(0);
		$active_sheet= $excel2->getActiveSheet();
        switch ($edition) {
			case "demande_simple" :
				traitement_demande_simple_edition($active_sheet, $type, $idPret);
				break;
			case "synthese_demande" :
				traitement_synthese_demande_edition($active_sheet, $type, $idPret);
				break;
			case "notice" :
				traitement_notice_edition($active_sheet, $type, $idPret);
				break;
			case "qms" :
				traitement_qms_edition($active_sheet, $type, $idPret, $isCoEmprunteur);
				break;
			case "dpa_vie":
				traitement_dpa_vie_edition($active_sheet, $type, $idPret, $isCoEmprunteur,$signature);
				break;
			case "kyc":
				traitement_kyc_edition($active_sheet, $type, $idPret);
				break;
			case "refus":
				traitement_refus_edition($active_sheet, $type, $idPret);
				break;
			case "dbs":
				traitement_dbs_edition($active_sheet, $type, $idPret, $isCoEmprunteur);
				break;
			case "remboursement":
				traitement_remboursement_edition($active_sheet, $type, $idPret);
				break;	
			case "demande" :
				traitement_demande_edition($active_sheet, $type, $idPret, $isCoEmprunteur,$signature);
				break;
			case "coc" :
				traitement_coc_edition($active_sheet, $type, $idPret, $isCoEmprunteur,$signature);
				break;
			case "test_edition":
				test_edition($active_sheet, $type, $idPret, $isCoEmprunteur,$signature);
			
				break;
		}		

        // Save it as an excel 2003 file
		//output file
		$now = new DateTime("now");
		$nom_fichier = $CI->config->item('dir_pdf') . "edition_".$now->format("Y-m-d_H-i-s");
		$objWriter = PHPExcel_IOFactory::createWriter($excel2, 'Excel2007');
		$objWriter->save( $nom_fichier . $extension);
		
		return $nom_fichier;	            
    }
}        
if(!function_exists('generate_exportation')){
	function generate_exportation($id_pret,$exportation,$type_exportation = "excel",$extension = ".xlsx"){
		$CI =& get_instance();
		$filename = $exportation. "_" . $id_pret ;
		$nomFinale = generate_publipostage_exportation($CI, $id_pret,  $exportation,$extension);
		if($type_exportation== "excel"){
			$CI->load->library('excel');        
			$excel2 = PHPExcel_IOFactory::createReader('Excel2007');	        
			$excel2 = $excel2->load($nomFinale. $extension);
			$objWriter = PHPExcel_IOFactory::createWriter($excel2, 'Excel2007');
			if ($extension == ".xlsm") {
					header('Content-Type: application/vnd.ms-excel.sheet.macroEnabled.12');
			} else {
					header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			}			
			header('Content-Disposition: attachment;filename="'. $filename.$extension.'"');
			header('Cache-Control: max-age=0');
			$objWriter->save('php://output');
			unlink($nomFinale. $extension);
		}
		else{
			$nomEdition = $nomFinale. $extension;
			$out = $nomFinale.".pdf";
			//génération du PDF
			exec('unoconv -o '.$out.' '. $nomEdition);
			//lecture du fichier générer
			sleep(1); // give the time it needs to convert into pdf
			$content = file_get_contents($out);	
			header("Content-Disposition: inline; filename=$filename");
			header("Content-type: application/pdf");
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
			readfile($out);					
			sleep(1); // give time to read the file before deleting it		
			unlink($nomFinale.$extension);
			unlink($out);    		
		}
		return true;
	}

}
if (!function_exists('generate_edition')) {
    function generate_edition($idPret, $edition, $isCoEmprunteur = false, $signature = "", $extension = ".xlsx") {
        $CI =& get_instance();
		
		$filename = $edition. "_" . $idPret ;
		 
		//Générer le fichier en utilisant $nomEdition
		$nomFinale = generate_publipostage_edition($CI, $idPret,  $edition, $isCoEmprunteur, $extension,$signature);
			
		if ($CI->config->item('is_linux')) {
			$nomEdition = $nomFinale. $extension;
			$out = $nomFinale.".pdf";
			//génération du PDF
			exec('unoconv -o '.$out.' '. $nomEdition);
			//lecture du fichier générer
			sleep(1); // give the time it needs to convert into pdf
			$content = file_get_contents($out);	
			header("Content-Disposition: inline; filename=$filename");
			header("Content-type: application/pdf");
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
			readfile($out);					
			sleep(1); // give time to read the file before deleting it		
			unlink($nomFinale.$extension);
			unlink($out);    			
		} else {		
			$CI->load->library('excel');        
	        $excel2 = PHPExcel_IOFactory::createReader('Excel2007');	        
	        $excel2 = $excel2->load($nomFinale. $extension);
			$objWriter = PHPExcel_IOFactory::createWriter($excel2, 'Excel2007');
			if ($extension == ".xlsm") {
				header('Content-Type: application/vnd.ms-excel.sheet.macroEnabled.12');
			} else {
				header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			}			
			header('Content-Disposition: attachment;filename="'. $filename.$extension.'"');
			header('Cache-Control: max-age=0');
			$objWriter->save('php://output');
			unlink($nomFinale. $extension);
			
		}
		return true;
    }
  }
  if(!function_exists("get_information_edition_by_id_pret")){
	function get_information_edition_by_id_pret($id_pret,$is_co=false){
		$CI =& get_instance();
		
		$CI->load->model('demande_cred/pret_model', 'pret');
		$info_pret = $CI->pret->get_tous_information_pret_edition($id_pret,$is_co);
		return $info_pret[0];
	}
  }
 if(!function_exists("conversion_en_lettre")){
function conversion_en_lettre($sasie)
{
	$lettres_retour='';
	$sasie=trim($sasie);
	$nombre='';
	$laSsasie=explode(' ',$sasie);
	foreach ($laSsasie as $partie)
	$nombre.=$partie;

	
	$nb=strlen($nombre);
	for ($i=0;$i<=$nb;)
	{
		if(substr($nombre,$i,1)==0)
		{
		$nombre=substr($nombre,$i+1);
		$nb=$nb-1;
		}
		elseif(substr($nombre,$i,1)<>0)
		{
		$nombre=substr($nombre,$i);
		break;
		}
	}
	$nb=strlen($nombre);
	
	switch ($nb)
	{
			case 0:
				$lettres_retour='zéro';
			case 1:
			if ($nombre==0)
			{
				$lettres_retour='zéro';
			break;
			}
			elseif($nombre<>0)
			{
				
				$lettres_retour= Unite($nombre);
			break;
			}

			case 2:
			$unite=substr($nombre,1);
			$dizaine=substr($nombre,0,1);
				$lettres_retour = Dizaine(0,$nombre,$unite,$dizaine);
			break;

			case 3:
			$unite=substr($nombre,2);
			$dizaine=substr($nombre,1,1);
			$centaine=substr($nombre,0,1);
				$lettres_retour = Centaine(0,$nombre,$unite,$dizaine,$centaine);
			break;

			#cas des milles
			case ($nb>3 and $nb<=6):
			$unite=substr($nombre,$nb-1);
			$dizaine=substr($nombre,($nb-2),1);
			$centaine=substr($nombre,($nb-3),1);
			$mille=substr($nombre,0,($nb-3));
				$lettres_retour = Mille($nombre,$unite,$dizaine,$centaine,$mille);
			break;

			#cas des millions
			case ($nb>6 and $nb<=9):
			$unite=substr($nombre,$nb-1);
			$dizaine=substr($nombre,($nb-2),1);
			$centaine=substr($nombre,($nb-3),1);
			$mille=substr($nombre,-6);
			$million=substr($nombre,0,$nb-6);
				$lettres_retour = Million($nombre,$unite,$dizaine,$centaine,$mille,$million);
			break;

			#cas des milliards
			case ($nb>9 and $nb<=12):
			$unite=substr($nombre,$nb-1);
			$milliard=substr($nombre,0,$nb-9);
				$lettres_retour = Milliard($nombre,$milliard);
			break;

	}
	if (!empty($lettres_retour))
		return $lettres_retour;
	}
}
if (!function_exists('Milliard')) {
	function Milliard($nombre,$milliard)
	{
		 $chiffre=array(1=>"un",2=>"deux",3=>"trois",4=>"quatre",5=>"cinq",6=>"six",7=>"sept",8=>"huit",9=>"neuf",10=>"dix",11=>"onze",12=>"douze",13=>"treize",14=>"quatorze",15=>"quinze",16=>"seize",17=>"dix-sept",18=>"dix-huit",19=>"dix-neuf",20=>"vingt",30=>"trente",40=>"quarante",50=>"cinquante",60=>"soixante",70=>"soixante-dix",80=>"quatre-vingt",90=>"quatre-vingt-dix");
		$lettre_milliard = "";
		$reste=substr($nombre,-9);
		if(strlen($milliard)==1){
			$lettre_milliard.=$chiffre[$milliard];
			if ($milliard == 1){
				$lettre_milliard.=' milliard ';
			}else{
				$$lettre_milliard.=' milliards ';
			}
		}
		elseif (strlen($milliard)>1)
		{
			//echo $nombre;
			$lettre_milliard = conversion_en_lettre(intval($milliard))."milliards";
		}
		$result_temp = "";
		if(intval($reste)!=0){
			$result_temp = conversion_en_lettre(intval($reste));
			
		}
		return $lettre_milliard." ".$result_temp;
	}

}
if (!function_exists('Million')) {
	function Million($nombre,$unite,$dizaine,$centaine,$mille,$million)
	{
		 $chiffre=array(1=>"un",2=>"deux",3=>"trois",4=>"quatre",5=>"cinq",6=>"six",7=>"sept",8=>"huit",9=>"neuf",10=>"dix",11=>"onze",12=>"douze",13=>"treize",14=>"quatorze",15=>"quinze",16=>"seize",17=>"dix-sept",18=>"dix-huit",19=>"dix-neuf",20=>"vingt",30=>"trente",40=>"quarante",50=>"cinquante",60=>"soixante",70=>"soixante-dix",80=>"quatre-vingt",90=>"quatre-vingt-dix");

		$cent=substr($nombre,-3);
		$reste=substr($nombre,-6);
		$retour = "";
		if (strlen($million)==1)
		{
			$mille=substr($nombre,1,3);
			$retour.=$chiffre[$million];
			if ($million == 1){
				$retour.=' million ';
			}else{
				$retour.=' millions ';
			}
		}
		elseif (strlen($million)==2)
		{
			$mille=substr($nombre,2,3);
			$nombre=substr($nombre,0,2);
			//echo $nombre;
				$diz = Dizaine(0,$nombre,$unite,$dizaine);
				$retour.=$diz;
			$retour.=' millions ';
		}
		elseif (strlen($million)==3)
		{
			$mille=substr($nombre,3,3);
			$nombre=substr($nombre,0,3);
				$centee = Centaine(0,$nombre,$unite,$dizaine,$centaine);
				$retour.=$centee;
			$retour.=' millions ';
		}

		#recuperation des cens dans nombre

		#suppression des zéros qui précéderaient le $reste
		$nb=strlen($reste);
		for ($i=0;$i<=$nb;)
		{
			if(substr($reste,$i,1)==0)
			{
			$reste=substr($reste,$i+1);
			$nb=$nb-1;
			}
			elseif(substr($reste,$i,1)<>0)
			{
			$reste=substr($reste,$i);
			break;
			}
		}
		$nb=strlen($reste);
		#si tous les chiffres apres les milions =000000 on affiche x million
		if ($nb==0)
		;
		else
		{
			#Gestion des milles
			#suppression des zéros qui précéderaient les milles dans $mille
			$nb=strlen($mille);
			for ($i=0;$i<=$nb;)
			{
				if(substr($mille,$i,1)==0)
				{
				$mille=substr($mille,$i+1);
				$nb=$nb-1;
				}
				elseif(substr($mille,$i,1)<>0)
				{
				$mille=substr($mille,$i);
				break;
				}
			}
			#le nombre de caract que comporte le nombre saisi de sa forme sans espace et sans 0 au début
			$nb=strlen($mille);
			#echo '<br />nb='.$nb.'<br />';
			if ($nb==0)
			;
			#AffichageResultat($enLettre);
			elseif ($nb==1)
			{
				if ($mille==1)
				$retour.=' mille ';
				else
				{
					$unite = Unite($mille);
					$retour.= $unite;
					$retour.=' mille ';
				}
			}
			elseif ($nb==2)
			{
				$id = Dizaine(1,$mille,$unite,$dizaine);
				$retour.= $id;
				$retour.=' mille ';
			}
			elseif ($nb==3)
			{
				$cente = Centaine(1,$mille,$unite,$dizaine,$centaine);
				$retour.=$cente ;
				$retour.=' mille ';
			}
			#Gestion des cents
			#suppression des zéros qui précéderaient les cents dans $cent
			$nb=strlen($cent);
			for ($i=0;$i<=$nb;)
			{
				if(substr($cent,$i,1)==0)
				{
					$cent=substr($cent,$i+1);
					$nb=$nb-1;
				}
				elseif(substr($cent,$i,1)<>0)
				{
					$cent=substr($cent,$i);
				break;
				}
			}
			#le nombre de caract que comporte le nombre saisi de sa forme sans espace et sans 0 au début
			$nb=strlen($cent);
			#echo '<br />nb='.$nb.'<br />';
			if ($nb==0)
			;
			#AffichageResultat($enLettre);
			elseif ($nb==1){
				$unite = Unite($cent);
				$retour.=$unite;
			}
			elseif ($nb==2){
				$diz = Dizaine(0,$cent,$unite,$dizaine);
				$retour.=$diz;
			}
			elseif ($nb==3){
				$ce = Centaine(0,$cent,$unite,$dizaine,$centaine);
				$retour.=$ce;
			}
		}
		return $retour;
		}
	}
if (!function_exists('Mille')) {
	function Mille($nombre,$unite,$dizaine,$centaine,$mille)
	{
			$retour = "";
		 $chiffre=array(1=>"un",2=>"deux",3=>"trois",4=>"quatre",5=>"cinq",6=>"six",7=>"sept",8=>"huit",9=>"neuf",10=>"dix",11=>"onze",12=>"douze",13=>"treize",14=>"quatorze",15=>"quinze",16=>"seize",17=>"dix-sept",18=>"dix-huit",19=>"dix-neuf",20=>"vingt",30=>"trente",40=>"quarante",50=>"cinquante",60=>"soixante",70=>"soixante-dix",80=>"quatre-vingt",90=>"quatre-vingt-dix");
		
	if (strlen($mille)==1)
	{
		$cent=substr($nombre,1);
		#si ce chiffre=1
		if ($mille==1)
		$retour .='';
		#si ce chiffre<>1
		elseif($mille<>1)
		$retour .=$chiffre[$mille];
	}
	elseif (strlen($mille)>1)
	{
		if (strlen($mille)==2)
		{
			$cent=substr($nombre,2);
			$nombre=substr($nombre,0,2);
		
			$disz = Dizaine(1,$nombre,$unite,$dizaine);
			$retour .= $disz;
		}
		if (strlen($mille)==3)
		{
			$cent=substr($nombre,3);
			$nombre=substr($nombre,0,3);
			#echo $nombre;
			$decee = Centaine(1,$nombre,$unite,$dizaine,$centaine);
			$retour .=$decee ;
		}
	}
	$retour .=' mille ';
	#recuperation des cens dans nombre
	#suppression des zéros qui précéderaient la saisie
	$nb=strlen($cent);
	for ($i=0;$i<=$nb;)
	{
		if(substr($cent,$i,1)==0)
		{
		$cent=substr($cent,$i+1);
		$nb=$nb-1;
		}
		elseif(substr($cent,$i,1)<>0)
		{
		$cent=substr($cent,$i);
		break;
		}
	}
	#le nombre de caract que comporte le nombre saisi de sa forme sans espace et sans 0 au début
	$nb=strlen($cent);
	#echo '<br />nb='.$nb.'<br />';
	if ($nb==0)
	;//AffichageResultat($enLettre);
	elseif ($nb==1){
		$uni = Unite($cent);
		$retour .= $uni;
	}
	elseif ($nb==2){
		$dev = Dizaine(0,$cent,$unite,$dizaine);
		$retour .= $dev;
	}
	elseif ($nb==3){
		$cet = Centaine(0,$cent,$unite,$dizaine,$centaine);
		$retour .= $cet;
	}
	return $retour;

	}
}
if (!function_exists('Centaine')) {
function Centaine($inmillier,$nombre,$unite,$dizaine,$centaine)
{
 $chiffre=array(1=>"un",2=>"deux",3=>"trois",4=>"quatre",5=>"cinq",6=>"six",7=>"sept",8=>"huit",9=>"neuf",10=>"dix",11=>"onze",12=>"douze",13=>"treize",14=>"quatorze",15=>"quinze",16=>"seize",17=>"dix-sept",18=>"dix-huit",19=>"dix-neuf",20=>"vingt",30=>"trente",40=>"quarante",50=>"cinquante",60=>"soixante",70=>"soixante-dix",80=>"quatre-vingt",90=>"quatre-vingt-dix");
$unite=substr($nombre,2);
$dizaine=substr($nombre,1,1);
$centaine=substr($nombre,0,1);
$retour = "";
#comme 700
if ($unite==0 and $dizaine==0)
{
if ($centaine==1)
$retour.=' cent';
elseif ($centaine<>1)
		{
				if ($inmillier == 0)
					$retour.=($chiffre[$centaine].' cents').' ';
				if ($inmillier == 1)
					$retour.=($chiffre[$centaine].' cent').' ';
		}
}
#comme 705
elseif ($unite<>0 and $dizaine==0)
{
if ($centaine==1)
$retour.=('cent '.$chiffre[$unite]).' ';
elseif ($centaine<>1)
$retour.=($chiffre[$centaine].' cent '.$chiffre[$unite]).' ';
}
//comme 750
elseif ($unite==0 and $dizaine<>0)
{
#recupération des dizaines
$nombre=substr($nombre,1);
//echo '<br />nombre='.$nombre.'<br />';
if ($centaine==1)
{
$retour.='cent ';
	$ret = Dizaine(0,$nombre,$unite,$dizaine).' ';
	$retour.=$ret;
}
elseif ($centaine<>1)
{
$retour.=$chiffre[$centaine].' cent ';
$t_d = Dizaine(0,$nombre,$unite,$dizaine).' ';
$retour.=$t_d ;

}

}
#comme 695
elseif ($unite<>0 and $dizaine<>0)
{
$nombre=substr($nombre,1);

if ($centaine==1)
{
$retour.=' cent ';
$det = Dizaine(0,$nombre,$unite,$dizaine).' ';
$retour.=$det ;
}

elseif ($centaine<>1)
{
$retour.=($chiffre[$centaine].' cent ');
$tddd= Dizaine(0,$nombre,$unite,$dizaine).' ';
$retour.= $tddd;
}
}
return $retour;
}
}
if (!function_exists('Dizaine')) {
function Dizaine($inmillier,$nombre,$unite,$dizaine)
{

$chiffre=array(1=>"un",2=>"deux",3=>"trois",4=>"quatre",5=>"cinq",6=>"six",7=>"sept",8=>"huit",9=>"neuf",10=>"dix",11=>"onze",12=>"douze",13=>"treize",14=>"quatorze",15=>"quinze",16=>"seize",17=>"dix-sept",18=>"dix-huit",19=>"dix-neuf",20=>"vingt",30=>"trente",40=>"quarante",50=>"cinquante",60=>"soixante",70=>"soixante-dix",80=>"quatre-vingt",90=>"quatre-vingt-dix");
$retour ="";
$unite=substr($nombre,1);
$dizaine=substr($nombre,0,1);

#comme 70
if ($unite==0)
{
$val=$dizaine.'0';
$retour .=$chiffre[$val];
		if ($inmillier == 0 && $val == 80){
			$retour .='s ';
		}
		$retour .=' ';
}
#comme 71
elseif ($unite<>0)
#dizaine different de 9
if ($dizaine<>9 and $dizaine<>7)
{
if ($dizaine==1)
{
$val=$dizaine.$unite;
$retour .=$chiffre[$val].' ';
}
else
{
$val=$dizaine.'0';
if ($unite == 1 && $dizaine <> 8){
$retour .=($chiffre[$val].' et '.$chiffre[$unite]).' ';
}else{
$retour .=($chiffre[$val].'-'.$chiffre[$unite]).' ';
}
}
}
#dizaine =9
elseif ($dizaine==9)
$retour .=($chiffre[80].'-'.$chiffre['1'.$unite]).' ';
elseif ($dizaine==7)
{
if ($unite == 1){
	$retour .=($chiffre[60].' et '.$chiffre['1'.$unite]).' ';
}else{
	$retour .=($chiffre[60].'-'.$chiffre['1'.$unite]).' ';
}
}
return $retour;
}
}
if (!function_exists('Unite')) {  
function Unite($unite)
{
$chiffre=array(1=>"un",2=>"deux",3=>"trois",4=>"quatre",5=>"cinq",6=>"six",7=>"sept",8=>"huit",9=>"neuf",10=>"dix",11=>"onze",12=>"douze",13=>"treize",14=>"quatorze",15=>"quinze",16=>"seize",17=>"dix-sept",18=>"dix-huit",19=>"dix-neuf",20=>"vingt",30=>"trente",40=>"quarante",50=>"cinquante",60=>"soixante",70=>"soixante-dix",80=>"quatre-vingt",90=>"quatre-vingt-dix");
	$retour="";
	
$retour.=($chiffre[$unite]).' ';
return $retour;
}
}

if(!function_exists("iconv_csv")){
	function iconv_csv($value){	  
		 //$retour = iconv('Windows-1252', 'UTF-8', $value);
		  $retour = iconv('UTF-8', 'Windows-1252', $value);
		 return $retour;
	}
}
if (!function_exists('array2csv')) {    
    function array2csv(array &$array,$headers){
		
       if (count($array) == 0) {
         return null;
       }
       ob_start();
       $df = fopen("php://output", 'w');
        $csv= array();
		$temp =  array();
		echo "\xEF\xBB\xBF";
        fputcsv($df, $headers ,";");
		unset($array[0]);
        foreach($array as $row) {
					$csv = array();
					foreach(array_keys($row) as $col){
						$csv[] = iconv_csv($row[$col]);
                        //$csv[] = $row[$col];
					}					
					fputcsv($df, $csv ,';');
		}
				
      
       fclose($df);
       return ob_get_clean();
    }
} 
if (!function_exists('download_send_headers')) { 
    function download_send_headers($filename) {
        // disable caching
        $now = gmdate("D, d M Y H:i:s");
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
        header("Last-Modified: {$now} GMT");
        
        //contentType="text/csv;charset=Windows-1252#RC.csv" language="fr-FR" showheader="false"
    
        // force download  
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream;");
        header("Content-Type: application/download");
    
        // disposition / encoding on response body
        header("Content-Disposition: attachment;filename={$filename}");
        //header('Content-Encoding:UTF-8');
        header("Content-Transfer-Encoding: binary");
		 header("Content-Type : text/csv");
		header("language:fr-FR");
		//header("charset:Windows-1252#RC.csv");
		//header("languag:fr-FR");
			//echo "\xEF\xBB\xBF"; // UTF-8 BOM
    }
 } 
 if (!function_exists('query_to_array')) {
    function query_to_array($query, $headers= TRUE){
        $array = array();
        
        if ($headers) {
            $line = array();
            foreach ($query->list_fields() as $name) {
                $line[] = $name;
            }
            $array[] = $line;
        }
        foreach ($query->result_array() as $row) {
            $line = array();
            foreach ($row as $item) {
                $line[] = $item;
            }
            $array[] = $line;
        }
         
        return  $array;
    }
}

if( !function_exists('extraire_taux')){
    
    function extraire_taux($data){
        
        $value = 0;
        if( $data!="") {
          $tab = explode("%",$data);
          $value = $tab[0];
        }
        
        return $value;
    }
}

if( !function_exists('calculate_taux')){
    
    function calculate_taux($tx_annuel_ht, $tva=0, $tx_assurance){
        
      // $t = ($tx_annuel_ht + $tva + $tx_assurance)/100;
     $t =  calculate_taux_ttc($tx_annuel_ht,$tva) +  $tx_assurance/100;
     return $t;
    }
}
if( ! function_exists('calculate_taux_ttc')){
    function calculate_taux_ttc($tx_annuel_ht, $tva=0){
        
        /*Taux annuel HT * (1 + TVA / 100)*/
        //return  $tx_annuel_ht * ( 1+  $tva/100);
        return  ($tx_annuel_ht + $tva)/100;
        
    }
}

if( ! function_exists('calculate_mensualite')){
    
    function calculate_mensualite($mt_pret, $duree, $tx_annuel_ht, $tva, $tx_assurance= 0){
        
        $t = calculate_taux($tx_annuel_ht, $tva, $tx_assurance);
        $k= $mt_pret;
        $n= $duree;
        $mensuelite = ($k*$t/12)/(1-pow(1+$t/12,-$n));
        
        return $mensuelite;
    }    
}

if( ! function_exists('calculate_taux_endettement_reel')){
    
    function calculate_taux_endettement_reel($mt_pret, $duree, $tx_annuel_ht, $tva, $tx_assurance, $mt_salaire_net,$autre_revenu, $autre_revenu_comp){
          //$taux = 0;
         // taux = Echéance Mensuelle (Avec assurance)  / (Salaire emprunteur   +  Autres revenus Emprunteur  + Autres revenus Co emprunteur) 
          $taux = calculate_taux( $tx_annuel_ht, $tva, $tx_assurance);
          $mensualite = calcul_mensualite_pret($mt_pret, $duree, $taux);
          if( ($mt_salaire_net+$autre_revenu+ $autre_revenu_comp)> 0 )
          return  $mensualite / ($mt_salaire_net+$autre_revenu+ $autre_revenu_comp);
          return 0;
		  
		  
    }    
}

if( ! function_exists('calculate_taux_endettement_encours')){
    
    function calculate_taux_endettement_encours($charge_menage, $qc_menage,$taux_max ){
          $taux = 0;
         // -	Si le QC du ménage est supérieur à 0 : 
         //  Total charges du ménage  * Taux d’endettement maximum  (par défaut 33%) / QC du ménage : 
          if( $qc_menage > 0 )
          $taux = $charge_menage * $taux_max / $qc_menage;
          return $taux;
    }    
}

if(! function_exists('calculate_qc_menage')){
    
    function calculate_qc_menage($id_pret){
        $qc_menage=0;
        //Champ de texte format monétaire
        //Tiers de la somme des salaires emprunteur et Co-emprunteur et du QC autres revenus emprunteur et Co-emprunteur de cette section
        //  ($mt_salaire_net + $mt_salire_net_co +  $autre_revenu + $autre_revenu_co)/3 ;
        $CI =& get_instance();
        $charge = 0;
			$co_charge = 0;
			$autre_rev = 0;
			$co_autre_rev = 0;
			$sal_emp = 0;
			$sal_co_emp = 0;
			$taux = 0;
			$echeance = 0;
            $qc_co_autre_rev = 0;
            
            $CI->load->model('demande_cred/score_model');
        $info_taux = $CI->score_model->get_info_taux_de_charges($id_pret);
		if(count($info_taux)>0){
			
			if($info_taux[0]->echeance!=null)
			$echeance = $info_taux[0]->echeance;
			if($info_taux[0]->charge!=null)
			$charge = $info_taux[0]->charge;
			if($info_taux[0]->co_charge!=null)
			$co_charge = $info_taux[0]->co_charge;
			if($info_taux[0]->mt_autres_revenus_net!=null)
			$autre_rev = $info_taux[0]->mt_autres_revenus_net;
			if($info_taux[0]->co_autre_revenus!=null)
			$co_autre_rev = $info_taux[0]->co_autre_revenus;
			$t_max = 0.33; 
			if($info_taux[0]->mt_salaire_net!=null)
			$sal_emp = $info_taux[0]->mt_salaire_net;
			if($info_taux[0]->co_mt_salaire_net!=null)
			$sal_co_emp = $info_taux[0]->co_mt_salaire_net;
			$tot_charge_menage = $charge + $co_charge;
			if(35*$sal_emp/100>$autre_rev )
			$qc_autre_rev = $autre_rev;
			else $qc_autre_rev = 35*$sal_emp/100;
			if(35*$sal_co_emp/100>$co_autre_rev)
				$qc_co_autre_rev = $co_autre_rev;
			else $qc_co_autre_rev = 35*$sal_co_emp/100;
			$qc_menage = ($sal_emp+$sal_co_emp+$qc_autre_rev+$qc_co_autre_rev)/3;
			
          }
		//	$taux = ($echeance + $tot_charge_menage)*($t_max/$qc_menage);
		 return $qc_menage;
        
    }    
    
   }
  if(! function_exists('calculate_qc_autre_rev')){ 
   
   function calculate_qc_autre_rev($id_pret){
        
        //Champ de texte format monétaire
        //Tiers de la somme des salaires emprunteur et Co-emprunteur et du QC autres revenus emprunteur et Co-emprunteur de cette section
        //  ($mt_salaire_net + $mt_salire_net_co +  $autre_revenu + $autre_revenu_co)/3 ;
        $CI =& get_instance();
        $charge = 0;
			$co_charge = 0;
			$autre_rev = 0;
			$co_autre_rev = 0;
			$sal_emp = 0;
			$sal_co_emp = 0;
			$taux = 0;
			$echeance = 0;
            $qc_co_autre_rev = 0;
            
            $CI->load->model('demande_cred/score_model');
        $info_taux = $CI->score_model->get_info_taux_de_charges($id_pret);
		if(count($info_taux)>0){
			
			if($info_taux[0]->echeance!=null)
			$echeance = $info_taux[0]->echeance;
			$assurance = 0;
			$tx = 0;
			if($info_taux[0]->assurance!=null){
				$as = explode("%",$info_taux[0]->assurance);
				$assurance = $as[0];
			}
			$tx = ($info_taux[0]->taux_annuel+$assurance)/100;
			
			$echeance = calcul_mensualite_pret($info_taux[0]->salaire_net, $info_taux[0]->duree_pret, $tx);
			
			if($info_taux[0]->charge!=null)
			$charge = $info_taux[0]->charge;
			if($info_taux[0]->co_charge!=null)
			$co_charge = $info_taux[0]->co_charge;
			if($info_taux[0]->mt_autres_revenus_net!=null)
			$autre_rev = $info_taux[0]->mt_autres_revenus_net;
			if($info_taux[0]->co_autre_revenus!=null)
			$co_autre_rev = $info_taux[0]->co_autre_revenus;
			$t_max = 0.33; 
			if($info_taux[0]->mt_salaire_net!=null)
			$sal_emp = $info_taux[0]->mt_salaire_net;
			if($info_taux[0]->co_mt_salaire_net!=null)
			$sal_co_emp = $info_taux[0]->co_mt_salaire_net;
			$tot_charge_menage = $charge + $co_charge;
			if(35*$sal_emp/100>$autre_rev)
			$qc_autre_rev = $autre_rev;
			else $qc_autre_rev = 35*$sal_emp/100;
			if(35*$sal_co_emp/100>$co_autre_rev)
				$qc_co_autre_rev = $co_autre_rev;
			else $qc_co_autre_rev = 35*$sal_co_emp;
			//$qc_menage = ($sal_emp+$sal_co_emp+$qc_autre_rev+$qc_co_autre_rev)/3;
			
          }
		//	$taux = ($echeance + $tot_charge_menage)*($t_max/$qc_menage);
		 return $qc_autre_rev + $qc_co_autre_rev;
        
    }
 } 
 
 if(! function_exists('clear_radical_format')){ 
     function   clear_radical_format($radical)  {
        
        $tab = explode(' ', $radical);
        $str =  $tab[0].$tab[1];
        return ltrim($str,'0');
        
     }
     
 }
 
 if(! function_exists('clear_money_format')){ 
     function   clear_money_format($money)  {
        
        $tab = explode(' ', $money);
        $str =  $tab[0].$tab[1].$tab[2];
        return (double) ltrim($str,'0');
        
     }
     
 }
 
 if(! function_exists('clear_millier_format')){ 
     function   clear_millier_format($money)  {
        
        $tab = explode(' ', $money);
        $str =  $tab[0];
        if( count($tab) > 1){
        for($i=1; $i< count($tab);$i++)
        $str .= $tab[$i];
        }
        return (double) ltrim($str,'0');
        
     }
     
 }
 
 if(! function_exists('clear_numeric_format')){ 
     function   clear_numeric_format($input)  {
        
        return (int) ltrim($input,'0');
        
     }
     
 }
 
 if( ! function_exists("calculate_qc_menages")){
    
    
    function calculate_qc_menages($mt_salaire_net,$mt_autres_revenus_net,$co_mt_salaire_net,$co_autre_revenus){
        $qc_menage=0;
        $qc_autre_rev =0;
        $qc_co_autre_rev = 0;
        //$tot_charge_menage = $charge + $co_charge;
        
        if(35*$mt_salaire_net/100>$mt_autres_revenus_net)
			$qc_autre_rev = $mt_autres_revenus_net;
       else 
            $qc_autre_rev = 35*$mt_salaire_net/100;
			
       if(35*$co_mt_salaire_net/100>$co_autre_revenus)
				$qc_co_autre_rev = $co_autre_revenus;
        else $qc_co_autre_rev = 35*$co_mt_salaire_net/100;
		
      	$qc_menage = ($mt_salaire_net+$co_mt_salaire_net+$qc_autre_rev+$qc_co_autre_rev)/3;
   }
			
}

 
 