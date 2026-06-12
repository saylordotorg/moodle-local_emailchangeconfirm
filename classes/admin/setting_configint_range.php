<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_emailchangeconfirm\admin;

/**
 * An admin text setting that validates an integer is within an inclusive range.
 *
 * @package    local_emailchangeconfirm
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_configint_range extends \admin_setting_configtext {
    /** @var int Minimum allowed value (inclusive). */
    protected $min;

    /** @var int Maximum allowed value (inclusive). */
    protected $max;

    /** @var string Identifier of the language string used for the out-of-range error. */
    protected $errorstring;

    /**
     * Constructor.
     *
     * @param string $name Unqualified setting name.
     * @param string $visiblename Localised label.
     * @param string $description Localised description.
     * @param int $defaultsetting Default value.
     * @param int $min Minimum value (inclusive).
     * @param int $max Maximum value (inclusive).
     * @param string $errorstring Language string identifier for the range error message.
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, $min, $max, $errorstring) {
        $this->min = $min;
        $this->max = $max;
        $this->errorstring = $errorstring;
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW, 10);
    }

    /**
     * Validate the submitted value is an integer within the configured range.
     *
     * @param string $data
     * @return bool|string True if valid, otherwise the error message.
     */
    public function validate($data) {
        // Must be a strict integer representation (rejects 0-padded, decimals, blanks).
        if (!is_numeric($data) || (string)(int)$data !== (string)$data) {
            return get_string($this->errorstring, 'local_emailchangeconfirm');
        }
        $value = (int)$data;
        if ($value < $this->min || $value > $this->max) {
            return get_string($this->errorstring, 'local_emailchangeconfirm');
        }
        return true;
    }
}
