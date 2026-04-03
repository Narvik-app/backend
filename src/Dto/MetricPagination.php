<?php

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

final class MetricPagination {
  public function __construct(
      #[Groups(['metric'])]
      private int $currentPage,
      #[Groups(['metric'])]
      private int $itemsPerPage,
      #[Groups(['metric'])]
      private int $totalItems,
      #[Groups(['metric'])]
      private int $totalPages,
      #[Groups(['metric'])]
      private array $order
  )
  {
  }

  public function getCurrentPage(): int
  {
    return $this->currentPage;
  }

  public function setCurrentPage(int $currentPage): MetricPagination
  {
    $this->currentPage = $currentPage;
    return $this;
  }

  public function getItemsPerPage(): int
  {
    return $this->itemsPerPage;
  }

  public function setItemsPerPage(int $itemsPerPage): MetricPagination
  {
    $this->itemsPerPage = $itemsPerPage;
    return $this;
  }

  public function getTotalItems(): int
  {
    return $this->totalItems;
  }

  public function setTotalItems(int $totalItems): MetricPagination
  {
    $this->totalItems = $totalItems;
    return $this;
  }

  public function getTotalPages(): int
  {
    return $this->totalPages;
  }

  public function setTotalPages(int $totalPages): MetricPagination
  {
    $this->totalPages = $totalPages;
    return $this;
  }

  public function getOrder(): array
  {
    return $this->order;
  }

  public function setOrder(array $order): MetricPagination
  {
    $this->order = $order;
    return $this;
  }
}
