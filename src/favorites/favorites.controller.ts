import { Controller, Delete, Get, Param, Post, UseGuards } from '@nestjs/common';
import { CurrentUser } from '../common/decorators/current-user.decorator';
import { JwtAuthGuard } from '../common/guards/jwt-auth.guard';
import { AuthUser } from '../common/types/auth-user.type';
import { FavoritesService } from './favorites.service';

@Controller('favorites')
@UseGuards(JwtAuthGuard)
export class FavoritesController {
  constructor(private readonly favoritesService: FavoritesService) {}

  @Get()
  list(@CurrentUser() user: AuthUser) {
    return this.favoritesService.list(user.sub);
  }

  @Post(':contentId')
  add(@CurrentUser() user: AuthUser, @Param('contentId') contentId: string) {
    return this.favoritesService.add(user.sub, contentId);
  }

  @Delete(':contentId')
  remove(@CurrentUser() user: AuthUser, @Param('contentId') contentId: string) {
    return this.favoritesService.remove(user.sub, contentId);
  }
}
