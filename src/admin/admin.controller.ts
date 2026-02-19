import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Patch,
  Post,
  UseGuards,
} from '@nestjs/common';
import { CurrentUser } from '../common/decorators/current-user.decorator';
import { Roles } from '../common/decorators/roles.decorator';
import { JwtAuthGuard } from '../common/guards/jwt-auth.guard';
import { RolesGuard } from '../common/guards/roles.guard';
import { AuthUser } from '../common/types/auth-user.type';
import { AdminService } from './admin.service';
import { CreateContentDto } from './dto/create-content.dto';
import { SendPromotionDto } from './dto/send-promotion.dto';
import { UpdateContentDto } from './dto/update-content.dto';
import { UpsertSectionDto } from './dto/upsert-section.dto';

@Controller('admin')
@UseGuards(JwtAuthGuard, RolesGuard)
@Roles('ADMIN')
export class AdminController {
  constructor(private readonly adminService: AdminService) {}

  @Get('content')
  listCatalog() {
    return this.adminService.getCatalogForAdmin();
  }

  @Post('content')
  createContent(@Body() dto: CreateContentDto) {
    return this.adminService.createContent(dto);
  }

  @Patch('content/:id')
  updateContent(@Param('id') id: string, @Body() dto: UpdateContentDto) {
    return this.adminService.updateContent(id, dto);
  }

  @Delete('content/:id')
  deleteContent(@Param('id') id: string) {
    return this.adminService.deleteContent(id);
  }

  @Get('sections')
  listSections() {
    return this.adminService.getSectionsForAdmin();
  }

  @Post('sections')
  upsertSection(@Body() dto: UpsertSectionDto) {
    return this.adminService.upsertSection(dto);
  }

  @Post('promotions/send')
  sendPromotion(@CurrentUser() user: AuthUser, @Body() dto: SendPromotionDto) {
    return this.adminService.sendPromotion(user.sub, dto);
  }

  @Get('promotions')
  listPromotions() {
    return this.adminService.listPromotions();
  }
}
