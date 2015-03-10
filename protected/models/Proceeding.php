<?php

/**
 * Model yang merepresentasikan artikel prosiding
 *
 * @author abrari
 */
class Proceeding extends CModel {
    
    public $authors;
    public $year;
    public $title;
    public $editors;
    public $con_name;
    public $con_date;
    public $con_city;
    public $con_country;
    public $pub_city;
    public $pub_country;
    public $pub;    
    public $pages;
    
    public function attributeNames() {
        return array(
            'authors' => 'Pengarang',
            'year' => 'Tahun',
            'title' => 'Judul artikel',
            'editors' => 'Editor',
            'con_name' => 'Nama konferensi',
            'con_date' => 'Tanggal konferensi',
            'con_city' => 'Kota konferensi',
            'con_country' => 'Negara konferensi',
            'pub_city' => 'Kota penerbit',
            'pub_country' => 'Negara penerbit',
            'pub' => 'Penerbit',
            'pages' => 'Halaman',
        );
    }

}
