<?php

namespace App\Support;

class BanyumasRegionData
{
    public const PROVINCE = 'Jawa Tengah';

    public const REGENCY = 'Kabupaten Banyumas';

    /**
     * Official 2-digit district (kecamatan) codes of Kabupaten Banyumas (Kemendagri 33.02.xx).
     *
     * @var array<string, string>
     */
    public const DISTRICT_CODES = [
        'Lumbir' => '01',
        'Wangon' => '02',
        'Jatilawang' => '03',
        'Rawalo' => '04',
        'Kebasen' => '05',
        'Kemranjen' => '06',
        'Sumpiuh' => '07',
        'Tambak' => '08',
        'Somagede' => '09',
        'Kalibagor' => '10',
        'Banyumas' => '11',
        'Patikraja' => '12',
        'Purwojati' => '13',
        'Ajibarang' => '14',
        'Gumelar' => '15',
        'Pekuncen' => '16',
        'Cilongok' => '17',
        'Karanglewas' => '18',
        'Sokaraja' => '19',
        'Kembaran' => '20',
        'Sumbang' => '21',
        'Baturraden' => '22',
        'Kedungbanteng' => '23',
        'Purwokerto Selatan' => '24',
        'Purwokerto Barat' => '25',
        'Purwokerto Timur' => '26',
        'Purwokerto Utara' => '27',
    ];

    /**
     * Fallback district code for districts outside the Banyumas reference data.
     */
    public const UNKNOWN_DISTRICT_CODE = '00';

    /**
     * Map of 27 Districts (Kecamatan) in Kabupaten Banyumas and their Villages (Desa/Kelurahan).
     *
     * @var array<string, array<int, array{code: string, name: string}>>
     */
    public const DISTRICTS = [
        'Ajibarang' => [
            ['code' => '01', 'name' => 'Ajibarang Kulon'],
            ['code' => '02', 'name' => 'Ajibarang Wetan'],
            ['code' => '03', 'name' => 'Banjarsari'],
            ['code' => '04', 'name' => 'Cingebul'],
            ['code' => '05', 'name' => 'Darmakradenan'],
            ['code' => '06', 'name' => 'Jangrana'],
            ['code' => '07', 'name' => 'Kalibenda'],
            ['code' => '08', 'name' => 'Karangbawang'],
            ['code' => '09', 'name' => 'Kracak'],
            ['code' => '10', 'name' => 'Lesmana'],
            ['code' => '11', 'name' => 'Pancasan'],
            ['code' => '12', 'name' => 'Pancurendang'],
            ['code' => '13', 'name' => 'Pandansari'],
            ['code' => '14', 'name' => 'Sawangan'],
            ['code' => '15', 'name' => 'Tipar Kidul'],
        ],
        'Banyumas' => [
            ['code' => '01', 'name' => 'Binangun'],
            ['code' => '02', 'name' => 'Danaraja'],
            ['code' => '03', 'name' => 'Dawuhan'],
            ['code' => '04', 'name' => 'Kalisube'],
            ['code' => '05', 'name' => 'Karangrau'],
            ['code' => '06', 'name' => 'Kedung Uter'],
            ['code' => '07', 'name' => 'Kedunggede'],
            ['code' => '08', 'name' => 'Pasinggangan'],
            ['code' => '09', 'name' => 'Pekunden'],
            ['code' => '10', 'name' => 'Sudagaran'],
            ['code' => '11', 'name' => 'Papringan'],
            ['code' => '12', 'name' => 'Kecila'],
        ],
        'Baturraden' => [
            ['code' => '01', 'name' => 'Karangmangu'],
            ['code' => '02', 'name' => 'Karangsalam'],
            ['code' => '03', 'name' => 'Karangtengah'],
            ['code' => '04', 'name' => 'Kebumen'],
            ['code' => '05', 'name' => 'Kemutug Kidul'],
            ['code' => '06', 'name' => 'Kemutug Lor'],
            ['code' => '07', 'name' => 'Ketenger'],
            ['code' => '08', 'name' => 'Kutasari'],
            ['code' => '09', 'name' => 'Pandak'],
            ['code' => '10', 'name' => 'Purwosari'],
            ['code' => '11', 'name' => 'Rempoah'],
            ['code' => '12', 'name' => 'Pamijen'],
        ],
        'Cilongok' => [
            ['code' => '01', 'name' => 'Batuanten'],
            ['code' => '02', 'name' => 'Cikidang'],
            ['code' => '03', 'name' => 'Cilongok'],
            ['code' => '04', 'name' => 'Cipete'],
            ['code' => '05', 'name' => 'Jatisaba'],
            ['code' => '06', 'name' => 'Kalisari'],
            ['code' => '07', 'name' => 'Karanglo'],
            ['code' => '08', 'name' => 'Karangtengah'],
            ['code' => '09', 'name' => 'Kasegeran'],
            ['code' => '10', 'name' => 'Langon'],
            ['code' => '11', 'name' => 'Pekuncen'],
            ['code' => '12', 'name' => 'Panembangan'],
            ['code' => '13', 'name' => 'Pernasidi'],
            ['code' => '14', 'name' => 'Rancamaya'],
            ['code' => '15', 'name' => 'Sambirata'],
            ['code' => '16', 'name' => 'Sudimara'],
        ],
        'Gumelar' => [
            ['code' => '01', 'name' => 'Cihonje'],
            ['code' => '02', 'name' => 'Cilangkap'],
            ['code' => '03', 'name' => 'Ganjang'],
            ['code' => '04', 'name' => 'Gumelar'],
            ['code' => '05', 'name' => 'Karangkemojing'],
            ['code' => '06', 'name' => 'Kedungkanggu'],
            ['code' => '07', 'name' => 'Paningkaban'],
            ['code' => '08', 'name' => 'Samudra'],
            ['code' => '09', 'name' => 'Samudra Kulon'],
            ['code' => '10', 'name' => 'Tlaga'],
        ],
        'Jatilawang' => [
            ['code' => '01', 'name' => 'Bantar'],
            ['code' => '02', 'name' => 'Gentawangi'],
            ['code' => '03', 'name' => 'Gunungwetan'],
            ['code' => '04', 'name' => 'Karanganyar'],
            ['code' => '05', 'name' => 'Karanglewas'],
            ['code' => '06', 'name' => 'Kedungwringin'],
            ['code' => '07', 'name' => 'Margasana'],
            ['code' => '08', 'name' => 'Pekuncen'],
            ['code' => '09', 'name' => 'Tinggarjaya'],
            ['code' => '10', 'name' => 'Tunjung'],
            ['code' => '11', 'name' => 'Adisara'],
        ],
        'Kalibagor' => [
            ['code' => '01', 'name' => 'Kalibagor'],
            ['code' => '02', 'name' => 'Kalicupak Kidul'],
            ['code' => '03', 'name' => 'Kalicupak Lor'],
            ['code' => '04', 'name' => 'Kaliori'],
            ['code' => '05', 'name' => 'Kalisogra Kulon'],
            ['code' => '06', 'name' => 'Karangdadap'],
            ['code' => '07', 'name' => 'Pajerukan'],
            ['code' => '08', 'name' => 'Petir'],
            ['code' => '09', 'name' => 'Srowot'],
            ['code' => '10', 'name' => 'Suro'],
            ['code' => '11', 'name' => 'Wlahar Wetan'],
            ['code' => '12', 'name' => 'Pekaja'],
        ],
        'Karanglewas' => [
            ['code' => '01', 'name' => 'Babakan'],
            ['code' => '02', 'name' => 'Jipang'],
            ['code' => '03', 'name' => 'Karanggude Kulon'],
            ['code' => '04', 'name' => 'Karangkemiri'],
            ['code' => '05', 'name' => 'Karanglewas Kidul'],
            ['code' => '06', 'name' => 'Kediri'],
            ['code' => '07', 'name' => 'Pasir Kulon'],
            ['code' => '08', 'name' => 'Pasir Lor'],
            ['code' => '09', 'name' => 'Pasir Wetan'],
            ['code' => '10', 'name' => 'Singasari'],
            ['code' => '11', 'name' => 'Sunyalangu'],
            ['code' => '12', 'name' => 'Tamansari'],
            ['code' => '13', 'name' => 'Pangebatan'],
        ],
        'Kebasen' => [
            ['code' => '01', 'name' => 'Adisana'],
            ['code' => '02', 'name' => 'Bangsa'],
            ['code' => '03', 'name' => 'Cindaga'],
            ['code' => '04', 'name' => 'Gambarsari'],
            ['code' => '05', 'name' => 'Kalisalak'],
            ['code' => '06', 'name' => 'Kaliwedi'],
            ['code' => '07', 'name' => 'Karangsari'],
            ['code' => '08', 'name' => 'Kebasen'],
            ['code' => '09', 'name' => 'Mandirancan'],
            ['code' => '10', 'name' => 'Randegan'],
            ['code' => '11', 'name' => 'Sawangan'],
            ['code' => '12', 'name' => 'Tumiyang'],
        ],
        'Kedungbanteng' => [
            ['code' => '01', 'name' => 'Baseh'],
            ['code' => '02', 'name' => 'Beji'],
            ['code' => '03', 'name' => 'Dawuhan Kulon'],
            ['code' => '04', 'name' => 'Dawuhan Wetan'],
            ['code' => '05', 'name' => 'Kalikesur'],
            ['code' => '06', 'name' => 'Kalisalak'],
            ['code' => '07', 'name' => 'Karangnangka'],
            ['code' => '08', 'name' => 'Karangsalam Kidul'],
            ['code' => '09', 'name' => 'Kebocoran'],
            ['code' => '10', 'name' => 'Kedungbanteng'],
            ['code' => '11', 'name' => 'Keniten'],
            ['code' => '12', 'name' => 'Kutaliman'],
            ['code' => '13', 'name' => 'Melung'],
            ['code' => '14', 'name' => 'Windujaya'],
        ],
        'Kembaran' => [
            ['code' => '01', 'name' => 'Bantarwuni'],
            ['code' => '02', 'name' => 'Bojongsari'],
            ['code' => '03', 'name' => 'Dukuhwaluh'],
            ['code' => '04', 'name' => 'Karangsari'],
            ['code' => '05', 'name' => 'Karangsoka'],
            ['code' => '06', 'name' => 'Karangtengah'],
            ['code' => '07', 'name' => 'Kembaran'],
            ['code' => '08', 'name' => 'Kramat'],
            ['code' => '09', 'name' => 'Ledug'],
            ['code' => '10', 'name' => 'Linggasari'],
            ['code' => '11', 'name' => 'Pliken'],
            ['code' => '12', 'name' => 'Purbadana'],
            ['code' => '13', 'name' => 'Purwodadi'],
            ['code' => '14', 'name' => 'Sambeng Kulon'],
            ['code' => '15', 'name' => 'Sambeng Wetan'],
            ['code' => '16', 'name' => 'Tambaksari Kidul'],
        ],
        'Kemranjen' => [
            ['code' => '01', 'name' => 'Alasmalang'],
            ['code' => '02', 'name' => 'Grumanten'],
            ['code' => '03', 'name' => 'Karanggintung'],
            ['code' => '04', 'name' => 'Karangjati'],
            ['code' => '05', 'name' => 'Karangsalam'],
            ['code' => '06', 'name' => 'Kebarongan'],
            ['code' => '07', 'name' => 'Kecila'],
            ['code' => '08', 'name' => 'Kedungpring'],
            ['code' => '09', 'name' => 'Nusamangir'],
            ['code' => '10', 'name' => 'Pageralang'],
            ['code' => '11', 'name' => 'Petarangan'],
            ['code' => '12', 'name' => 'Sibalung'],
            ['code' => '13', 'name' => 'Sibrama'],
            ['code' => '14', 'name' => 'Sidamulya'],
            ['code' => '15', 'name' => 'Sirau'],
        ],
        'Lumbir' => [
            ['code' => '01', 'name' => 'Besuki'],
            ['code' => '02', 'name' => 'Canduk'],
            ['code' => '03', 'name' => 'Cidora'],
            ['code' => '04', 'name' => 'Cingebul'],
            ['code' => '05', 'name' => 'Cirahab'],
            ['code' => '06', 'name' => 'Dermaji'],
            ['code' => '07', 'name' => 'Karanggayam'],
            ['code' => '08', 'name' => 'Lumbir'],
            ['code' => '09', 'name' => 'Parungkamal'],
            ['code' => '10', 'name' => 'Kedunggede'],
        ],
        'Patikraja' => [
            ['code' => '01', 'name' => 'Kedungrandu'],
            ['code' => '02', 'name' => 'Kedungwringin'],
            ['code' => '03', 'name' => 'Kedungwuluh Kidul'],
            ['code' => '04', 'name' => 'Kedungwuluh Lor'],
            ['code' => '05', 'name' => 'Notog'],
            ['code' => '06', 'name' => 'Patikraja'],
            ['code' => '07', 'name' => 'Pegalongan'],
            ['code' => '08', 'name' => 'Sawangan Wetan'],
            ['code' => '09', 'name' => 'Sidabowa'],
            ['code' => '10', 'name' => 'Sokawera Kidul'],
            ['code' => '11', 'name' => 'Karanganyar'],
            ['code' => '12', 'name' => 'Karangendep'],
            ['code' => '13', 'name' => 'Wlahar Kulon'],
        ],
        'Pekuncen' => [
            ['code' => '01', 'name' => 'Banjaranyar'],
            ['code' => '02', 'name' => 'Candinegara'],
            ['code' => '03', 'name' => 'Cibangkong'],
            ['code' => '04', 'name' => 'Cikawung'],
            ['code' => '05', 'name' => 'Cikembulan'],
            ['code' => '06', 'name' => 'Glempang'],
            ['code' => '07', 'name' => 'Karangkemiri'],
            ['code' => '08', 'name' => 'Karangklesem'],
            ['code' => '09', 'name' => 'Krajan'],
            ['code' => '10', 'name' => 'Kranggan'],
            ['code' => '11', 'name' => 'Pasiraman Kidul'],
            ['code' => '12', 'name' => 'Pasiraman Lor'],
            ['code' => '13', 'name' => 'Pekuncen'],
            ['code' => '14', 'name' => 'Petahunan'],
            ['code' => '15', 'name' => 'Semedo'],
            ['code' => '16', 'name' => 'Tumiyang'],
        ],
        'Purwojati' => [
            ['code' => '01', 'name' => 'Galisalak'],
            ['code' => '02', 'name' => 'Gerduren'],
            ['code' => '03', 'name' => 'Kaliputih'],
            ['code' => '04', 'name' => 'Karangmangu'],
            ['code' => '05', 'name' => 'Karangtalun Kidul'],
            ['code' => '06', 'name' => 'Karangtalun Lor'],
            ['code' => '07', 'name' => 'Klapajati'],
            ['code' => '08', 'name' => 'Purwojati'],
            ['code' => '09', 'name' => 'Ravalo'],
            ['code' => '10', 'name' => 'Kaliwangi'],
        ],
        'Purwokerto Barat' => [
            ['code' => '01', 'name' => 'Bantarsoka'],
            ['code' => '02', 'name' => 'Karanglewas Lor'],
            ['code' => '03', 'name' => 'Kedungwuluh'],
            ['code' => '04', 'name' => 'Kober'],
            ['code' => '05', 'name' => 'Pasirmuncang'],
            ['code' => '06', 'name' => 'Pasir Kidul'],
            ['code' => '07', 'name' => 'Rejasari'],
        ],
        'Purwokerto Selatan' => [
            ['code' => '01', 'name' => 'Berkoh'],
            ['code' => '02', 'name' => 'Karangklesem'],
            ['code' => '03', 'name' => 'Karangpucung'],
            ['code' => '04', 'name' => 'Purwokerto Kulon'],
            ['code' => '05', 'name' => 'Purwokerto Kidul'],
            ['code' => '06', 'name' => 'Tanjung'],
            ['code' => '07', 'name' => 'Teluk'],
        ],
        'Purwokerto Timur' => [
            ['code' => '01', 'name' => 'Arcawinangun'],
            ['code' => '02', 'name' => 'Kranji'],
            ['code' => '03', 'name' => 'Mersi'],
            ['code' => '04', 'name' => 'Purwokerto Lor'],
            ['code' => '05', 'name' => 'Purwokerto Wetan'],
            ['code' => '06', 'name' => 'Sokanegara'],
        ],
        'Purwokerto Utara' => [
            ['code' => '01', 'name' => 'Bancarkembar'],
            ['code' => '02', 'name' => 'Bobosan'],
            ['code' => '03', 'name' => 'Grendeng'],
            ['code' => '04', 'name' => 'Karangwangkal'],
            ['code' => '05', 'name' => 'Pabuaran'],
            ['code' => '06', 'name' => 'Purwanegara'],
            ['code' => '07', 'name' => 'Sumampir'],
        ],
        'Rawalo' => [
            ['code' => '01', 'name' => 'Banjarparakan'],
            ['code' => '02', 'name' => 'Losari'],
            ['code' => '03', 'name' => 'Menganti'],
            ['code' => '04', 'name' => 'Pesawahan'],
            ['code' => '05', 'name' => 'Rawalo'],
            ['code' => '06', 'name' => 'Sanggreman'],
            ['code' => '07', 'name' => 'Sidamulih'],
            ['code' => '08', 'name' => 'Sukonondo'],
            ['code' => '09', 'name' => 'Tambaknegara'],
        ],
        'Sokaraja' => [
            ['code' => '01', 'name' => 'Banjaranyar'],
            ['code' => '02', 'name' => 'Banjarsari Kidul'],
            ['code' => '03', 'name' => 'Jompo Kulon'],
            ['code' => '04', 'name' => 'Kalikidang'],
            ['code' => '05', 'name' => 'Karangduren'],
            ['code' => '06', 'name' => 'Karangkedawung'],
            ['code' => '07', 'name' => 'Karangnanas'],
            ['code' => '08', 'name' => 'Karangrauh'],
            ['code' => '09', 'name' => 'Klahang'],
            ['code' => '10', 'name' => 'Lemberang'],
            ['code' => '11', 'name' => 'Pamijen'],
            ['code' => '12', 'name' => 'Sokaraja Kulon'],
            ['code' => '13', 'name' => 'Sokaraja Lor'],
            ['code' => '14', 'name' => 'Sokaraja Tengah'],
            ['code' => '15', 'name' => 'Sokaraja Wetan'],
            ['code' => '16', 'name' => 'Sokawera'],
            ['code' => '17', 'name' => 'Wiradadi'],
            ['code' => '18', 'name' => 'Kedondong'],
        ],
        'Somagede' => [
            ['code' => '01', 'name' => 'Kanding'],
            ['code' => '02', 'name' => 'Kemawi'],
            ['code' => '03', 'name' => 'Klinting'],
            ['code' => '04', 'name' => 'Piasa Kulon'],
            ['code' => '05', 'name' => 'Plana'],
            ['code' => '06', 'name' => 'Sokawera'],
            ['code' => '07', 'name' => 'Somagede'],
            ['code' => '08', 'name' => 'Somakaton'],
            ['code' => '09', 'name' => 'Tanggeran'],
        ],
        'Sumbang' => [
            ['code' => '01', 'name' => 'Banjarsari Kulon'],
            ['code' => '02', 'name' => 'Banjarsari Wetan'],
            ['code' => '03', 'name' => 'Banteran'],
            ['code' => '04', 'name' => 'Ciberem'],
            ['code' => '05', 'name' => 'Datar'],
            ['code' => '06', 'name' => 'Gandatapa'],
            ['code' => '07', 'name' => 'Karangcegak'],
            ['code' => '08', 'name' => 'Karanggintung'],
            ['code' => '09', 'name' => 'Karangturi'],
            ['code' => '10', 'name' => 'Kawungcarang'],
            ['code' => '11', 'name' => 'Kebanggan'],
            ['code' => '12', 'name' => 'Kedungmalang'],
            ['code' => '13', 'name' => 'Kotayasa'],
            ['code' => '14', 'name' => 'Limansari'],
            ['code' => '15', 'name' => 'Sikapat'],
            ['code' => '16', 'name' => 'Silado'],
            ['code' => '17', 'name' => 'Sumbang'],
            ['code' => '18', 'name' => 'Susukan'],
            ['code' => '19', 'name' => 'Tambaksogra'],
        ],
        'Sumpiuh' => [
            ['code' => '01', 'name' => 'Bogangin'],
            ['code' => '02', 'name' => 'Karanggedang'],
            ['code' => '03', 'name' => 'Kemiri'],
            ['code' => '04', 'name' => 'Ketanda'],
            ['code' => '05', 'name' => 'Kradenan'],
            ['code' => '06', 'name' => 'Kuntili'],
            ['code' => '07', 'name' => 'Lebeng'],
            ['code' => '08', 'name' => 'Nusadadi'],
            ['code' => '09', 'name' => 'Pandak'],
            ['code' => '10', 'name' => 'Selandaka'],
            ['code' => '11', 'name' => 'Sumpiuh'],
            ['code' => '12', 'name' => 'Kebokura'],
            ['code' => '13', 'name' => 'Banjarpanepen'],
            ['code' => '14', 'name' => 'Kreadingan'],
        ],
        'Tambak' => [
            ['code' => '01', 'name' => 'Buniayu'],
            ['code' => '02', 'name' => 'Gebangsari'],
            ['code' => '03', 'name' => 'Gumelar Kidul'],
            ['code' => '04', 'name' => 'Gumelar Lor'],
            ['code' => '05', 'name' => 'Kamulyan'],
            ['code' => '06', 'name' => 'Karangpetir'],
            ['code' => '07', 'name' => 'Karangpucung'],
            ['code' => '08', 'name' => 'Plangkapan'],
            ['code' => '09', 'name' => 'Prembun'],
            ['code' => '10', 'name' => 'Purwodadi'],
            ['code' => '11', 'name' => 'Watuagung'],
            ['code' => '12', 'name' => 'Pesantren'],
        ],
        'Wangon' => [
            ['code' => '01', 'name' => 'Banteran'],
            ['code' => '02', 'name' => 'Cikakak'],
            ['code' => '03', 'name' => 'Jambu'],
            ['code' => '04', 'name' => 'Jurangmangu'],
            ['code' => '05', 'name' => 'Klapagading'],
            ['code' => '06', 'name' => 'Klapagading Kulon'],
            ['code' => '07', 'name' => 'Pangadegan'],
            ['code' => '08', 'name' => 'Randegan'],
            ['code' => '09', 'name' => 'Rawaheng'],
            ['code' => '10', 'name' => 'Wangon'],
            ['code' => '11', 'name' => 'Windunegara'],
            ['code' => '12', 'name' => 'Wlahar'],
        ],
    ];

    /**
     * Get list of all district names as array.
     *
     * @return array<int, string>
     */
    public static function getDistricts(): array
    {
        return array_keys(self::DISTRICTS);
    }

    /**
     * Get villages list for a given district.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public static function getVillagesForDistrict(?string $district): array
    {
        if (! $district || ! isset(self::DISTRICTS[$district])) {
            return [];
        }

        return self::DISTRICTS[$district];
    }

    /**
     * Get list of all district names.
     *
     * @return array<string, string>
     */
    public static function getDistrictOptions(): array
    {
        $keys = array_keys(self::DISTRICTS);

        return array_combine($keys, $keys);
    }

    /**
     * Get villages for a given district.
     *
     * @return array<string, string>
     */
    public static function getVillageOptions(?string $district): array
    {
        if (! $district || ! isset(self::DISTRICTS[$district])) {
            return [];
        }

        $options = [];
        foreach (self::DISTRICTS[$district] as $village) {
            $options[$village['name']] = $village['name'];
        }

        return $options;
    }

    /**
     * Get the official 2-digit code of a district (kecamatan), case-insensitive.
     */
    public static function getDistrictCode(?string $district): ?string
    {
        if (! $district) {
            return null;
        }

        foreach (self::DISTRICT_CODES as $name => $code) {
            if (strcasecmp($name, trim($district)) === 0) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Find 2-digit code for a given district and village name (supports both argument orders).
     */
    public static function findVillageCode(?string $arg1, ?string $arg2): ?string
    {
        if (! $arg1 || ! $arg2) {
            return null;
        }

        // Determine which argument is district and which is village name
        $district = isset(self::DISTRICTS[$arg1]) ? $arg1 : (isset(self::DISTRICTS[$arg2]) ? $arg2 : null);
        $villageName = ($district === $arg1) ? $arg2 : $arg1;

        if (! $district || ! isset(self::DISTRICTS[$district])) {
            // Search all districts for village name if district not exact match
            foreach (self::DISTRICTS as $dist => $villages) {
                foreach ($villages as $v) {
                    if (strcasecmp($v['name'], $arg1) === 0 || strcasecmp($v['name'], $arg2) === 0) {
                        return $v['code'];
                    }
                }
            }

            return null;
        }

        foreach (self::DISTRICTS[$district] as $village) {
            if (strcasecmp($village['name'], $villageName) === 0) {
                return $village['code'];
            }
        }

        return null;
    }
}
