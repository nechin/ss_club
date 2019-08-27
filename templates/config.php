<style>
    .td_header_title {
        display: inline-block;
        font-size: 16px;
        font-weight: bold;
        padding-bottom: 10px;
    }
</style>
<div class="wrap">
<h2>Настройки клуба</h2>
<table class="form-table">
    <form method="post" action="options.php" name="form">
    <?php wp_nonce_field('club-config-options');?>
    <input type="hidden" name="option_page" value="club-config" />

    <tr>
        <td>
            <span class="td_header_title">Доступ к рубрикам клуба для незарегистрированных посетителей</span><br>
            <label>
                <input type="radio" name="club_unregister_access" value="1" <?php checked(1, $club_unregister_access); ?>/>
                Закрыт (при клике в меню предлагается зарегистрироваться или залогиниться)
            </label><br>
            <label>
                <input type="radio" name="club_unregister_access" value="2" <?php checked(2, $club_unregister_access); ?>/>
                Приоткрыт (показываются названия и краткие описания записей)
            </label>
        </td>
    </tr>

    <tr>
        <td>
            <span class="td_header_title">Видимость записей в рубриках клуба для посетителей, не имеющих доступа к этим записям</span><br>
            <label>
                <input type="radio" name="club_notaccess_display" value="1" <?php checked(1, $club_notaccess_display); ?>/>
                Записи скрыты (отсутствуют в списке)
            </label><br>
            <label>
                <input type="radio" name="club_notaccess_display" value="2" <?php checked(2, $club_notaccess_display); ?>/>
                Записи приоткрыты (показываются названия и краткие описания для посетителей без доступа)
            </label>
        </td>
    </tr>

    <tr>
        <td>
            <span class="td_header_title">Сообщение при попытке входа в раздел или доступа к записи клуба, открытой только для зарегистрированных пользователей</span><br>
            <textarea name="club_unregister_message" cols="70"><?= $club_unregister_message; ?></textarea>
        </td>
    </tr>

    <tr>
        <td>
            <span class="td_header_title">Предложение купить доступ при попытке открыть раздел или запись</span><br>
            <textarea name="club_offer_message" cols="70"><?= $club_offer_message; ?></textarea>
        </td>
    </tr>

    <tr>
        <td>
            <span class="td_header_title">Элементы записи клуба</span><br>
            <label>
                <input type="checkbox" name="club_display_full_image" value="1" <?php checked(1, $club_display_full_image); ?>/>
                Выводить миниатюру на всю ширину записи клуба
            </label>
        </td>
    </tr>

    <tr>
        <td>
            <p class="submit">
                <input type="hidden" name="action" value="update" />
                <input type="submit" class="button-primary" name="Submit" value="Сохранить" />
            </p>
        </td>
    </tr>
    </form>
</table>
</div>