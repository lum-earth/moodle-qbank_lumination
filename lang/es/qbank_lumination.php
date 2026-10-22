<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish language strings for qbank_lumination.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['backtobank'] = 'Volver al banco de preguntas';
$string['categoryname'] = 'Generadas por Lumination AI: {$a->file} ({$a->date})';
$string['difficulty'] = 'Dificultad';
$string['difficulty_easy'] = 'Fácil';
$string['difficulty_hard'] = 'Difícil';
$string['difficulty_medium'] = 'Media';
$string['generate'] = 'Generar preguntas';
$string['generateintro'] = 'Sube un documento y Lumination AI redacta preguntas de opción múltiple a partir de él. Se guardan en una categoría nueva dentro de "{$a}", para que las revises antes de añadirlas a un cuestionario.';
$string['generatetab'] = 'Generar con IA';
$string['generatetitle'] = 'Generar preguntas con IA';
$string['importfailed'] = 'No se pudieron añadir las preguntas generadas al banco de preguntas.';
$string['jobcanleave'] = 'Puedes salir de esta página. La generación continúa en segundo plano y esta página se actualiza cada pocos segundos.';
$string['jobcompleted'] = '{$a->created} preguntas a partir de "{$a->file}" están en una categoría nueva del banco de preguntas. Revísalas antes de añadirlas a un cuestionario.';
$string['jobfailed'] = 'La generación de preguntas a partir de "{$a->file}" ha fallado.';
$string['jobqueued'] = 'La generación de {$a->count} preguntas a partir de "{$a->file}" empezará en breve.';
$string['jobrunning'] = 'Redactando {$a->count} preguntas a partir de "{$a->file}". Suele tardar menos de un minuto.';
$string['jobskipped'] = 'Se omitieron {$a->skipped} preguntas porque sus opciones de respuesta estaban incompletas.';
$string['jobwaitingcron'] = 'Este trabajo aún no ha empezado. La generación de preguntas se ejecuta en segundo plano mediante el cron de Moodle, así que pide al administrador del sitio que compruebe que el cron funciona.';
$string['language'] = 'Idioma de las preguntas';
$string['noprovider'] = 'El proveedor Lumination AI no está configurado en este sitio. Un administrador puede añadirlo en Administración del sitio > IA > Proveedores de IA con una clave de la API de AI Tutor.';
$string['noquestions'] = 'La API de AI Tutor no devolvió preguntas utilizables para este documento.';
$string['nosourcefile'] = 'Falta el documento de origen.';
$string['pluginname'] = 'Generador de preguntas Lumination AI';
$string['privacy:metadata:aitutor'] = 'El documento de origen se envía a la API de AI Tutor de Lumination AI para redactar las preguntas. Se borra de Moodle después del trabajo.';
$string['privacy:metadata:aitutor:document'] = 'El contenido del documento de origen subido.';
$string['privacy:metadata:jobs'] = 'Registros de los trabajos de generación de preguntas.';
$string['privacy:metadata:jobs:filename'] = 'El nombre del documento de origen.';
$string['privacy:metadata:jobs:status'] = 'El estado del trabajo.';
$string['privacy:metadata:jobs:timecreated'] = 'La fecha y hora en que se inició el trabajo.';
$string['privacy:metadata:jobs:userid'] = 'El ID del usuario que inició el trabajo.';
$string['questioncount'] = 'Número de preguntas';
$string['sourcefile'] = 'Documento de origen';
$string['sourcefile_help'] = 'Un archivo PDF, Word (.docx) o de texto con el material que deben cubrir las preguntas.';
$string['sourcepages'] = 'Fuente: página {$a} del documento.';
$string['viewquestions'] = 'Ver las preguntas';
