<?php

namespace Tests\Unit;

use App\Support\TituloDePagina;
use PHPUnit\Framework\TestCase;

class TituloDePaginaTest extends TestCase
{
    public function test_prefiere_el_titulo_de_livewire_y_luego_el_explicito(): void
    {
        $this->assertSame('Sedes', TituloDePagina::resolver('Sedes', 'Otro', '<h2>Cabecera</h2>'));
        $this->assertSame('Nueva papeleta', TituloDePagina::resolver(null, 'Nueva papeleta', '<h2>Cabecera</h2>'));
        $this->assertSame('Sedes', TituloDePagina::resolver('  Sedes  '));
    }

    public function test_toma_el_primer_encabezado_del_slot_header(): void
    {
        $header = "<div>\n  <h2 class=\"font-bold\">\n      Detalle de\n      papeleta\n  </h2>\n  <button>Imprimir</button>\n</div>";

        $this->assertSame('Detalle de papeleta', TituloDePagina::resolver(null, null, $header));
    }

    public function test_sin_encabezado_usa_todo_el_texto_del_slot_sin_etiquetas(): void
    {
        $this->assertSame('Mi perfil', TituloDePagina::resolver(null, null, '<span>Mi</span> <b>perfil</b>'));
    }

    public function test_decodifica_entidades_html(): void
    {
        $this->assertSame('Programar & revisar', TituloDePagina::resolver(null, null, '<h2>Programar &amp; revisar</h2>'));
    }

    public function test_devuelve_null_cuando_no_hay_nada_que_mostrar(): void
    {
        $this->assertNull(TituloDePagina::resolver());
        $this->assertNull(TituloDePagina::resolver('', '   ', ''));
        $this->assertNull(TituloDePagina::resolver(null, null, '<h2>   </h2>'));
    }
}
