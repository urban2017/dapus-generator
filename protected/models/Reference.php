<?php

/**
 * Reference creator (Journal, Proceeding, BookChapter)
 *
 * @author abrari
 */
class Reference extends CModel {
    
    public function attributeNames() {
        return array();
    }

    public function createJournal($data)
    {
        $journal = new Journal();
        $journal->authors = $data['author'];
        $journal->year    = $data['issued']['date-parts'][0][0];
        $journal->title   = $data['title'][0];
        $journal->volume  = $data['volume'];
        $journal->issue   = $data['issue'];
        $journal->pages   = $data['page'];
        $journal->doi     = $data['DOI'];

        $journalNames = isset($data['container-title']) ? $data['container-title'] : array();
        $journalName = "";
        if(count($journalNames) >= 2) { // Jurnal dan singkatannya
            if(strlen($journalNames[0]) > strlen($journalNames[1]))
                $journalName = $journalNames[1]; // Ambil singkatannya
            else
                $journalName = $journalNames[0];
        } elseif(count($journalNames) == 1) {
            $journalName = $journalNames[0];
        } else {
            $journalName = '[Jurnal tidak diketahui]';
        }

        $journal->journal = $journalName;

        CVarDumper::dump($journal, 10, TRUE);
        
        return $journal;
    }

    public function createBookChapter($data)
    {
        try {
            $bookData = WebAPI::searchBookData($data['ISBN'][0]);
        } catch (CException $e) {
            $bookData = null;
        }

        $chapter = new BookChapter();
        $chapter->authors = $data['author'];
        $chapter->year    = $data['issued']['date-parts'][0][0];
        $chapter->title   = $data['title'][0];            
        $chapter->pages   = $data['page'];

        if($bookData !== null) {
            $chapter->book_title = StringHelper::titleCase($bookData['title']);
            $chapter->pub        = $bookData['publisher'];
            $chapter->pub_city   = (strpos($bookData['city'], ",") === false) ? $bookData['city'] : explode(",", $bookData['city'])[0]; 
            $chapter->pub_country= WebAPI::searchCityData($chapter->pub_city);

            // editors uses Stanford NER
            // sometimes editors in author, sometimes in title~
            $combined = $bookData['author'] . ' ' . $bookData['title'];
            $ner_result = StringHelper::NER($combined);

            $chapter->editors    = StringHelper::parseNerPerson($ner_result);

        } else {
            $chapter->book_title = StringHelper::titleCase($data['container-title'][0]);
            $chapter->pub        = $data['publisher'];
        }

        CVarDumper::dump($chapter, 10, TRUE);
        
        return $chapter;
    }

    public function createProceeding($data)
    {
        try {
            $bookData = WebAPI::searchBookData($data['ISBN'][0]);
        } catch (CException $e) {
            $bookData = null;
        }

        $proc = new Proceeding();
        $proc->authors = $data['author'];
        $proc->year    = $data['issued']['date-parts'][0][0];
        $proc->title   = $data['title'][0];            
        $proc->proc_name  = StringHelper::titleCase($data['container-title'][0]);
        $proc->pages   = null;        // sigh. no info available

        if($bookData !== null) {
            $proc->pub        = $bookData['publisher'];
            $proc->pub_city   = (strpos($bookData['city'], ",") === false) ? $bookData['city'] : explode(",", $bookData['city'])[0]; 
            $proc->pub_city   = preg_replace("/[^A-Za-z0-9 \-']/", "", $proc->pub_city); // additional filtering
            $proc->pub_country= WebAPI::searchCityData($proc->pub_city);

            // editors, con_date, con_city uses Stanford NER
            // sometimes editors in author, sometimes in title~
            $combined = $bookData['author'] . ' ' . $bookData['title'];
            $ner_result = StringHelper::NER($combined);

            $proc->editors    = StringHelper::parseNerPerson($ner_result);
            $proc->con_date   = StringHelper::parseNerDate($ner_result);
            $proc->con_city   = StringHelper::parseNerLocation($ner_result);

        } else {
            $proc->pub        = $data['publisher'];
        }

        CVarDumper::dump($proc, 10, TRUE);
        
        return $proc;
    }        

}