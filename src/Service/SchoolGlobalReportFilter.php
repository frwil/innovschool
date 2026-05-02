<?php

namespace App\Service;

class SchoolGlobalReportFilter
{
    private SchoolGlobalReportFilterDto $filter;

    public function __construct(SchoolGlobalReportFilterDto $filter)
    {
        $this->filter = $filter;
    }

    public function getFilter(): SchoolGlobalReportFilterDto
    {
        return $this->filter;
    }

    public function isEmpty(): bool
    {
        foreach (get_object_vars($this->filter) as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}