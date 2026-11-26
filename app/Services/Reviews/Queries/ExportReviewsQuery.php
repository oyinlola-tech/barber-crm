<?php

namespace App\Services\Reviews\Queries;

use App\Interfaces\Repositories\Reviews\ReviewRepositoryInterface;
use Illuminate\Support\LazyCollection;

class ExportReviewsQuery
{
    public function __construct(private readonly ReviewRepositoryInterface $reviews) {}

    /** @return LazyCollection<int, array{id:int,rating:int,body:string,source:string,reviewed_at:string,customer_name:string,customer_phone:string,visit_at:string}> */
    public function execute(int $businessId): LazyCollection
    {
        return $this->reviews->cursorForBusinessExport($businessId);
    }
}
