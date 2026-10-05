<?php
 
class Create_products_table {
 
    private $_lava;
    protected $dbforge;
 
    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }
 
    public function up()
    {
        if ($this->_lava->dbforge->table_exists('products')) {
            return;
        }
 
        $this->_lava->dbforge
            ->add_field([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => TRUE,
                    'auto_increment' => TRUE,
                    'null'           => FALSE,
                ],
                'product_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => FALSE,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => TRUE,
                ],
                'price' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => FALSE,
                ],
                'quantity' => [
                    'type'    => 'INT',
                    'default' => 0,
                    'null'    => FALSE,
                ],
                'created_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => TRUE,
                ],
            ])
            ->add_key('id', primary: TRUE)
            ->create_table('products');
    }
 
    public function down()
    {
        $this->_lava->dbforge->drop_table('products');
    }
}
