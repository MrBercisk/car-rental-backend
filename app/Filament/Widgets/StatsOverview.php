<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Testimonial;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Mobil', Product::count())
                ->description(Product::where('is_available', true)->count() . ' tersedia')
                ->icon('heroicon-o-truck')
                ->color('success'),

            Stat::make('Total Kategori', Category::count())
                ->icon('heroicon-o-tag')
                ->color('info'),

            Stat::make('Total Testimoni', Testimonial::where('is_active', true)->count())
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('warning'),

            Stat::make('Pesan Belum Dibaca', Contact::where('is_read', false)->count())
                ->icon('heroicon-o-envelope')
                ->color('danger'),
        ];
    }
}
