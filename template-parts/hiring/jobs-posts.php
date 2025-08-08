<div class="jobs pagination-section">
    <div class="container">
        <?php
        $paged = get_query_var('paged') ?: 1;
        $args = [
            'post_type'      => 'jobs',
            'posts_per_page' => 10,
            'paged'          => $paged,
            'lang'           => function_exists('pll_current_language') ? pll_current_language() : '',
        ];
        $jobs_query = new WP_Query($args);

        if ($jobs_query->have_posts()) :
            ?>
            <div class="jobs-listings">
                <?php while ($jobs_query->have_posts()) : $jobs_query->the_post(); ?>
                    <div class="job-listing">
                        <div class="job-title" onclick="toggleJobDetails(this)">
                            <div class="title">
                                <h6><?php the_title(); ?></h6>
                                <span><?php printf(esc_html__('%s ago', 'cob_theme'), human_time_diff(get_the_time('U'), current_time('timestamp'))); ?></span>
                            </div>
                            <button>
                                <i class="fas fa-chevron-down toggle-icon"></i>
                                <span><?php esc_html_e('View details', 'cob_theme'); ?></span>
                            </button>
                        </div>
                        <div class="job-details" style="display: none;">
                            <p><strong><?php esc_html_e('Job Qualifications', 'cob_theme'); ?>:</strong></p>
                            <ul>
                                <?php
                                $qualifications = get_post_meta(get_the_ID(), 'job_qualifications', true);
                                if (!empty($qualifications) && is_array($qualifications)) {
                                    foreach ($qualifications as $qualification) {
                                        echo '<li>' . esc_html($qualification) . '</li>';
                                    }
                                } else {
                                    echo '<li>' . esc_html__('No qualifications have been added.', 'cob_theme') . '</li>';
                                }
                                ?>
                            </ul>
                            <div class="bottom-job">
                                <ul class="bottom-tags">
                                    <?php
                                    $job_tags = get_the_terms(get_the_ID(), 'job_tag');
                                    if (!empty($job_tags) && !is_wp_error($job_tags)) {
                                        foreach ($job_tags as $tag) {
                                            echo '<li><span>' . esc_html($tag->name) . '</span></li>';
                                        }
                                    }
                                    ?>
                                </ul>
                                <button class="apply-button">
                                    <?php esc_html_e('Apply Now', 'cob_theme'); ?>
                                    <svg width="8" height="16" viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.330221 15.1671C0.765134 15.611 1.47033 15.611 1.90523 15.1671L7.34836 9.60613C8.21749 8.71814 8.21716 7.27931 7.34769 6.39178L1.90122 0.832995C1.46632 0.389002 0.761125 0.389002 0.326201 0.832995C-0.108734 1.27688 -0.108734 1.99663 0.326201 2.44051L4.98775 7.19827C5.42276 7.64215 5.42265 8.3619 4.98775 8.80578L0.330221 13.5595C-0.104714 14.0034 -0.104714 14.7232 0.330221 15.1671Z" fill="white"/></svg>
                                </button>
                                <div class="job-overlay"></div>
                                <div class="job-popup">
                                    <form class="jobApplicationForm" method="post" enctype="multipart/form-data">
                                        <ul>
                                            <li>
                                                <h2><?php esc_html_e('Apply for Job', 'cob_theme'); ?></h2>
                                                <button type="button" class="close-job-popup">
                                                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M31.375 8.625L8.625 31.375M8.625 8.625L31.375 31.375" stroke="white" stroke-width="2.4375" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </button>
                                            </li>
                                            <li>
                                                <div><h3><?php the_title(); ?></h3></div>
                                            </li>
                                            <li>
                                                <div class="input-div">
                                                    <label for="full_name_<?php the_ID(); ?>"><?php esc_html_e('Full Name', 'cob_theme'); ?></label>
                                                    <input type="text" id="full_name_<?php the_ID(); ?>" name="full_name" placeholder="<?php esc_attr_e('Enter your full name', 'cob_theme'); ?>" required>
                                                </div>
                                                <div class="input-div">
                                                    <label for="phone_<?php the_ID(); ?>"><?php esc_html_e('Phone Number', 'cob_theme'); ?></label>
                                                    <input type="tel" id="phone_<?php the_ID(); ?>" name="phone" placeholder="<?php esc_attr_e('Enter your phone number', 'cob_theme'); ?>" required>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="input-div">
                                                    <label for="experience_years_<?php the_ID(); ?>"><?php esc_html_e('Years of Experience', 'cob_theme'); ?></label>
                                                    <input type="text" id="experience_years_<?php the_ID(); ?>" name="experience_years" placeholder="<?php esc_attr_e('e.g., 5', 'cob_theme'); ?>">
                                                </div>
                                                <div class="input-div">
                                                    <label for="email_<?php the_ID(); ?>"><?php esc_html_e('Email', 'cob_theme'); ?></label>
                                                    <input type="email" id="email_<?php the_ID(); ?>" name="email" placeholder="<?php esc_attr_e('Enter your email address', 'cob_theme'); ?>" required>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="input-div2">
                                                    <label for="address_<?php the_ID(); ?>"><?php esc_html_e('Address', 'cob_theme'); ?></label>
                                                    <input type="text" id="address_<?php the_ID(); ?>" name="address" placeholder="<?php esc_attr_e('Enter your address', 'cob_theme'); ?>">
                                                </div>
                                            </li>
                                            <li>
                                                <div class="input-div2">
                                                    <label for="upload_<?php the_ID(); ?>"><?php esc_html_e('Attach CV', 'cob_theme'); ?></label>
                                                    <div class="file-input-container">
                                                        <input type="file" name="resume" id="upload_<?php the_ID(); ?>" class="file-input" accept=".pdf,.doc,.docx">
                                                        <div class="file-input-label">
                                                            <div class="file-svg"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 12.5H6.15167C6.46744 12.5 6.75609 12.6785 6.89718 12.961L7.26912 13.7057C7.4102 13.9882 7.69884 14.1667 8.01461 14.1667H11.9846C12.3002 14.1667 12.5888 13.9883 12.7299 13.706L13.1026 12.9607C13.2437 12.6783 13.5323 12.5 13.8479 12.5H17.5" stroke="#B49164" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.0007 6.2041V10.3708" stroke="#B49164" stroke-linecap="round" stroke-linejoin="round"/><path d="M11.6673 7.87077L10.0007 6.2041L8.33398 7.87077" stroke="#B49164" stroke-linecap="round" stroke-linejoin="round"/><path fill-rule="evenodd" clip-rule="evenodd" d="M15 2.5C16.3807 2.5 17.5 3.61929 17.5 5V15C17.5 16.3807 16.3807 17.5 15 17.5H5C3.61929 17.5 2.5 16.3807 2.5 15V5C2.5 3.61929 3.61929 2.5 5 2.5H15Z" stroke="#B49164" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                                            <div class="file-text"><p><span><?php esc_html_e('Choose file', 'cob_theme'); ?></span><?php esc_html_e('or drag it here', 'cob_theme'); ?></p></div>
                                                        </div>
                                                    </div>
                                                    <span class="file-span"><?php esc_html_e('Allowed file types: PDF, DOC, DOCX.', 'cob_theme'); ?></span>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="input-div2">
                                                    <label for="overview_<?php the_ID(); ?>"><?php esc_html_e('More Details', 'cob_theme'); ?></label>
                                                    <textarea name="additional_details" id="overview_<?php the_ID(); ?>" placeholder="<?php esc_attr_e('Enter any other details.', 'cob_theme'); ?>"></textarea>
                                                </div>
                                            </li>
                                            <li>
                                                <input type="hidden" name="job_id" value="<?php the_ID(); ?>">
                                                <input type="hidden" name="action" value="submit_job_application">
                                                <?php wp_nonce_field( 'submit_job_application', 'job_application_nonce' ); ?>
                                                <button type="submit"><?php esc_html_e( 'Send Application', 'cob_theme' ); ?></button>
                                            </li>
                                        </ul>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>

                <div class="pagination">
                    <?php echo paginate_links(['total' => $jobs_query->max_num_pages, 'prev_text' => __('&laquo; Previous', 'cob_theme'), 'next_text' => __('Next &raquo;', 'cob_theme')]); ?>
                </div>
            </div><!-- end .jobs-listings -->
            <?php wp_reset_postdata(); ?>
        <?php else : ?>
            <p><?php esc_html_e('There are no jobs available at the moment.', 'cob_theme'); ?></p>
        <?php endif; ?>
    </div>
</div>
