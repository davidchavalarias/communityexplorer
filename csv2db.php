<?php
echo '<meta http-equiv="Content-type" content="text/html; charset=UTF-8"/>';
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
include("parametres.php");
include("../common/library/fonctions_php.php");

/* Creation des tables */


$base = new PDO("sqlite:".$dbname);    
echo 'creating tables<br/>';

if ($drop_tables){
/* on efface les tables et on la recrée*/

$query = "DROP TABLE terms;";
$results = $base->query($query);

$query = "DROP TABLE scholars;";
$results = $base->query($query);

$query = "DROP TABLE scholars2terms;";
$results = $base->query($query);

$query = "DROP TABLE ". $scholars_db.";";
$results = $base->query($query);


$query = "CREATE TABLE terms (id integer,stemmed_key text,term text,variations text,occurrences integer)";
$results = $base->query($query);

$query = "CREATE TABLE scholars2terms (scholar text,term_id interger)";
$results = $base->query($query);


$query = "CREATE TABLE scholars (id integer,unique_id text,country text,
    title text,first_name text,initials text,last_name text,position text,
    keywords text,keywords_ids text,nb_keywords integer,homepage text,
    css_member text,css_voter text,lab text,affiliation text,lab2 text,affiliation2 text,want_whoswho text)";
$results = $base->query($query);
}
$output_file=$fichier."_out.csv";

echo 'creating '.$output.'<br/>';

$output= fopen($output_file, "w","UTF-8");

global $data,$la;


$row = 1;

pt("opening ".$fichier);

if (($handle = fopen($fichier, "r","UTF-8")) !== FALSE) {
    
    /* On crée les entrée de la table avec la première ligne */
    $query = "CREATE TABLE ".$scholars_db." (";
    $subquery=""; /* partie de la requete pour alimenter la base plus bas */
    $la=array(); // liste des noms de colonne du csv
    $data = fgetcsv($handle, 1000, ",");
    
    $count=0;
    $label_list=array();
    $terms_array=array();// tableau pour remplir la table terms
    
    // on prépare les colonnes pour la table de données brutes
    $num = count($data);
    pt("number of columns: ".$num);
        for ($c=0; $c < $num; $c++) {
            $temp=split('--',$data[$c]);
            $label=str_replace(' ', '_',trim($temp[0]));
            $label=str_replace(':', '',$label);
            $label=str_replace("'", '',$label);
            $label=str_replace("?", '',$label);
            $label=str_replace("-", '_',$label);
            $label=str_replace(",", '_',$label);
            $label=str_replace("(", '',$label);
            $label=str_replace(")", '',$label);
            
            
            $subquery=$subquery.$label.',';
            $la[$label]=$c;
            if ($label_list[$label]==1){ // si le label existe déjà on lui colle un post_fix pour éviter les doublon de fields
                $label.='_2';
            }
            $label_list[$label]=1;
            $query =$query.$label.' text,';
            
            pt($label);
            
        }                
    $query =substr($query, 0, -1);       
    $query = $query . ')';
    
    $subquery = substr($subquery, 0, -1);
    pt("sous requete: " . $subquery);
    pt("Creating table with : " . $query);
    $results = $base->query($query);   

    $heading[]='corp_id';
    $heading[]='doc_id';
    $heading[]='title';
    $heading[]='doc_acrnm';
    $heading[]='abstract';
    $heading[]='keywords';
    fputcsv($output,$heading,$file_sep,'"');
    
    // on analyse le csv
    $ngram_id=array();
    $scholar_count=0;
    while (($data = fgetcsv($handle, 1000, $file_sep)) !== FALSE) {
        // analyse des mots clefs
            $scholar_count+=1;
            $scholar=str_replace(' ','_',$data[$la['First_Name']].' '.$data[$la['Second_fist_name_initials']].' '.$data[$la['Last_Name']]);                        
            $scholar_ngrams='';
            $scholar_ngrams_ids='';
            $scholar_ngrams_count=0;
            
            $keywords=$data[$la['Keywords']];
            $keywords=str_replace(".",'',$keywords);
            $keywords=str_replace("-",' ',$keywords);
            $ngrams=split('(,|;)',$keywords);
            
            foreach ($ngrams as $ngram) {
            $ngram=str_replace("'", " ",trim($ngram));    
            if ((strlen($ngram) < 50)&&(strlen($ngram) > 0)) {
                $gram_array = split(' ', $ngram);
                $ngram_stemmed = '';
                if (count($gram_array)==2){
                    natsort($gram_array);
                }
                foreach ($gram_array as $gram) {
                    $ngram_stemmed.=stemword(trim(strtolower($gram)), $language, 'UTF_8').' ';
                }
                $ngram_stemmed=trim($ngram_stemmed);
                if (array_key_exists($ngram_stemmed, $terms_array)) {// si la forme stemmed du ngram a déjà été rencontrée
                    if (array_key_exists($ngram, $terms_array[$ngram_stemmed])) {// si la forme pleine a déjà été rencontrée
                        $terms_array[$ngram_stemmed][$ngram]+=1;
                    } else {
                        $terms_array[$ngram_stemmed][$ngram] = 1;
                    }
                } else {
                    $terms_array[$ngram_stemmed][$ngram] = 1;
                    $ngram_id[$ngram_stemmed]=count($ngram_id)+1;
                }
                $scholar_ngrams.=$ngram.',';
                $scholar_ngrams_ids.=$ngram_id[$ngram_stemmed].',';   
                $scholar_ngrams_count+=1;
                $query = "INSERT INTO scholars2terms (scholar,term_id) VALUES ('".$scholar."',".$ngram_id[$ngram_stemmed].")";                
                $results = $base->query($query);                           
            }
        }         
        
        $scholar_ngrams=str_replace("'", " ", $scholar_ngrams); //
        
        
        if(($data[$la['First_Name']]!=null)&&($data[$la['Last_Name']]!=null)){
        
        $query = "INSERT INTO scholars (id,unique_id,country,title,first_name,initials,last_name,position,keywords,keywords_ids,
            nb_keywords, homepage,css_member,css_voter,lab,affiliation,lab2,affiliation2,want_whoswho) VALUES (".$scholar_count.",'".
            $scholar."','".$data[$la['Country']]."','".$data[$la['Title']].
        "','".$data[$la['First_Name']]."','".$data[$la['Second_fist_name_initials']]."','".$data[$la['Last_Name']]."','".
        $data[$la['Position']]."','".$scholar_ngrams."','".substr($scholar_ngrams_ids,0,-1)."','".$scholar_ngrams_count.
                "','".$data[$la['Homepage']]."','".$data[$la['CSS_Member_AUTO']]."','".$data[$la['CSS_Voters_AUTO']]
                ."','".$data[$la['Lab']]."','".$data[$la['Institutional_affiliation']]
                ."','".$data[$la['Second_lab']]."','".$data[$la['Second_Institutional_affiliation']]
                ."','".$data[$la['Do_you_want_to_appear_in_the_CSS_Whos_who_']]."')";
        pt($query);
        
        
        $results = $base->query($query);
        
        }

        //
        
        
        $num = count($data);        
        $row++;
        $profile=array();
        $corp_id=str_replace(' ','_',$data[$la['Country']]);
        $doc_id=$data[$la['itemId']];
        $title=trim($data[$la['First_Name']].' '.$data[$la['Last_Name']]);
        $doc_acrnm=$title;
                
        $abstract=$data[$la['Country']].'.'.
        section('Affiliation','Lab,Institutional_affiliations_of_your_lab').
        section('Second Affiliation','Second_lab,Second_institutional_affiliation').
        section('Keywords','Keywords');
        $keywords=merge('Personal_Interest');
        
                
        $profile[]=$corp_id;
        $profile[]=$doc_id;
        $profile[]=$title;
        $profile[]=$doc_acrnm;
        $profile[]=$abstract;
        $profile[]=$keywords;
        
        
        if ($title!=null){
            fputcsv($output,$profile,',','"');
            pt($title);            
        }
            
        $values="'".$data[0]."'";
        for ($c = 1; $c < $num; $c++) {
            $values=$values.",'".$data[$c]."'";            
            }    
        
        $query = "INSERT INTO " . $scholars_db . "(".$subquery.") VALUES (".$values.")";
        /*$query = "INSERT INTO $scholars_db(ID, post_title, post_content, post_author, post_date, guid) 
                VALUES ('$number', '$title', '$content', '$author', '$date', '$url')";*/
        pt($query);
        $results = $base->query($query);                       
        if ($results ) {
            pt('requete OK');
        }
    }
    
    fclose($handle);
}

print_r($terms_array);
$id=0;
pt('inserting terms '.count($terms_array));
$stemmed_ngram_list=array_keys($terms_array);
for ($i=0;$i<count($stemmed_ngram_list);$i++){
//foreach ($terms_array as $stemmed_ngram -> $ngram_forms){
    $stemmed_ngram=$stemmed_ngram_list[$i];
    $ngram_forms=$terms_array[$stemmed_ngram];
    pt($id);
    $most_common_form=array_search(max($ngram_forms), $ngram_forms);
    pt($most_common_form);
    $most_common_form=str_replace('"','',$most_common_form);
    $variantes=array_keys($ngram_forms);
    $variations=implode ( '***' ,$variantes );
    //$query = "INSERT INTO terms (id,stemmed_key,term,variations,occurrences) VALUES ('".$id."','".$stemmed_ngram."','".$most_common_form."','".$variations."','".sum($ngram_forms).")";        
    
    $query = "INSERT INTO terms (id,stemmed_key,term,variations,occurrences) VALUES ('".$ngram_id[$stemmed_ngram]."','".$stemmed_ngram."','".$most_common_form."','".$variations."',".array_sum($ngram_forms).")";        
    
    pt($query);
    $results = $base->query($query);        
    
    
}



copy($output_file,'community.csv');
pt($output_file.' copied');
pt($row.' scholars processed');

function merge($fieldlist, $sep=', ') {
    global $data,$la;
    /* merge les champs dans $fieldlist séparés par des virgules en vérifiant qu'ils sont non vides */
    $string = '';
    $fields = split(',', $fieldlist);
    $data[$la[trim($fields[0])]];
    for ($i = 0; $i < count($fields); $i++) {
        if ($data[$la[trim($fields[$i])]] != null) {
            $string = $string . $data[$la[trim($fields[$i])]] . $sep;
        }
    }

    if (count($string) > count($sep)) {
        if (strcmp($sep, substr($string(-count($sep), -1))) == 0) {
            $string = substr($string, 0, -count($sep) - 1);
        }
    }
    return $string;
}

function section($name,$content,$sep=', '){
    /* merge les champs dans $fieldlist séparés par des virgules en vérifiant qu'ils sont non vides*/
    global $data,$la;
    
    $string='';        
    $temp=merge($content,$sep);
    if ($temp!=null){
            $string=''.$name.': '.$temp.'';
        }    
    return $string;   
}
        
?>
