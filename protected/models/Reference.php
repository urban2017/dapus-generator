<?php

/**
 * Reference creator (Journal, Proceeding, BookChapter)
 *
 * @author abrari
 */
class Reference extends CModel {
    
    public $type;

    public function attributeNames() {
        return array();
    }
    
    public function formatAuthors()
    {
        // format crossRef authors
        $authors = $this->makeAuthors();
        $authorsFormatted = array();
        
        if(count($authors) == 0) {
            return '[Anonim]';
        }
        
        $i = 0;
        foreach($authors as $author) {
            $name = $author['family'] . ' ' . StringHelper::initials($author['given']);
            $authorsFormatted[] = trim($name);
            
            $i++;
            if($i == 10) {
                $authorsFormatted[9] .= ' <i>et al</i>';
                break;
            }
        }
        
        $authorsString = implode(', ', $authorsFormatted);
        
        return $authorsString;
    }
    
    public function formatAuthorsInline()
    {
        // for inline citation (in text)
        $authors = $this->makeAuthors();
        $count = count($authors);
        
        if($count == 0) {
            return '[Anonim]';
        } else if($count == 1) {
            $name = $authors[0]['family'];
            return $name;
        } else if($count == 2) {
            $name  = $authors[0]['family'];
            $name .= ' dan ';
            $name .= $authors[1]['family'];
            return $name;            
        } else {
            $name = $authors[0]['family'] . ' <em>et al.</em>';
            return $name;            
        }
    }

    public function createJournal($data)
    {
        $journal = new Journal();
        $journal->type    = $data['type'];
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

        $journal->journal = StringHelper::titleCase($journalName);

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
        $chapter->type    = $data['type'];
        $chapter->authors = $data['author'];
        $chapter->year    = $data['issued']['date-parts'][0][0];
        $chapter->title   = $data['title'][0];            
        $chapter->pages   = $data['page'];

        if($bookData !== null) {
            $chapter->book_title = StringHelper::titleCase($bookData['title']);
            $chapter->pub        = $bookData['publisher'];
            $chapter->pub_city   = (strpos($bookData['city'], ",") === false) ? $bookData['city'] : reset(explode(",", $bookData['city']));
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
        $proc->type    = $data['type'];
        $proc->authors = $data['author'];
        $proc->year    = $data['issued']['date-parts'][0][0];
        $proc->title   = $data['title'][0];            
        $proc->proc_name  = StringHelper::titleCase($data['container-title'][0]);
        $proc->pages   = null;        // sigh. no info available

        if($bookData !== null) {
            $proc->pub        = $bookData['publisher'];
            $city = explode(",", $bookData['city']);
            $proc->pub_city   = reset($city); 
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

        return $proc;
    }
    
    public function formatCitation(){
        
    }
    
    public function formatInlineCitation() {
        return "(" . $this->formatAuthorsInline() . ' ' . CHtml::encode($this->year) . ')';
    }
    
    public function makeAuthors() {
        // convert from comma-separated author list to crossRef-compatible author array
        if(!is_string($this->authors) || is_array($this->authors)) return $this->authors;
        
        $authors_result = array();
        $authors = explode(",", $this->authors);
        
        foreach($authors as $author) {
            $name = explode(' ', trim($author));
            $lastName = array_pop($name);
        
            $authors_result[] = array('family'=>$lastName, 'given'=>implode(' ', $name));
        }
        
        return $authors_result;
    }
    
    public function unmakeAuthors() {
        // convert from crossRef author array to comma-separated string
        if(is_string($this->authors)) return $this->authors;
        
        $authors_result = array();
        
        foreach($this->authors as $author) {
            $authors_result[] = $author['given'] . ' ' . $author['family'];
        }
        
        return implode(", ", $authors_result);
    }

}
