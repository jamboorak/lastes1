from pathlib import Path
path = Path('my-bookings.php')
text = path.read_text(encoding='utf-8')
old_room = '''                                            <div class="item-card">
                                                    <div class="item-image">
                                                        <i class="fas fa-bed"></i>
                                                    </div>
                                                    <div class="item-details">
                                                        <h3 class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                                        <p class="item-meta">Good for <?php echo $item['capacity']; ?> guests</p>
                                                        <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                                                    </div>
                                                </div>
'''
new_room = '''                                            <div class="item-card">
                                                    <div class="item-image">
                                                        <?php if (!empty($item['image_url'])): ?>
                                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                                        <?php else: ?>
                                                            <i class="fas fa-bed"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="item-details">
                                                        <h3 class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                                        <p class="item-meta">Good for <?php echo $item['capacity']; ?> guests</p>
                                                        <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                                                    </div>
                                                </div>
'''
old_cottage = '''                                            <div class="item-card">
                                                    <div class="item-image">
                                                        <i class="fas fa-home"></i>
                                                    </div>
                                                    <div class="item-details">
                                                        <h3 class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                                        <p class="item-meta">Good for <?php echo $item['capacity']; ?> guests</p>
                                                        <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                                                    </div>
                                                </div>
'''
new_cottage = '''                                            <div class="item-card">
                                                    <div class="item-image">
                                                        <?php if (!empty($item['image_url'])): ?>
                                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                                        <?php else: ?>
                                                            <i class="fas fa-home"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="item-details">
                                                        <h3 class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                                        <p class="item-meta">Good for <?php echo $item['capacity']; ?> guests</p>
                                                        <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                                                    </div>
                                                </div>
'''
text = text.replace(old_room, new_room)
text = text.replace(old_cottage, new_cottage)
path.write_text(text, encoding='utf-8')
print('patched', text.count(new_room), text.count(new_cottage))
